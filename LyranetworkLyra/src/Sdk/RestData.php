<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace Lyranetwork\Lyra\Sdk;

use Lyranetwork\Lyra\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Lyra\Sdk\Tools as LyraTools;
use Lyranetwork\Lyra\Service\ConfigService;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;
use Lyranetwork\Lyra\Sdk\Rest\Api as LyraRest;
use Lyranetwork\Lyra\Sdk\Form\Request as LyraRequest;

use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RouterInterface;

use Sylius\Component\Resource\Repository\RepositoryInterface;

use Psr\Log\LoggerInterface;

class RestData
{
    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    public function __construct(
        ConfigService $configService,
        LoggerInterface $logger,
        RouterInterface $router,
        RepositoryInterface $customerRepository
    )
    {
        $this->configService = $configService;
        $this->logger = $logger;
        $this->router = $router;
        $this->customerRepository = $customerRepository;
    }

    public function getPrivateKey($instanceCode)
    {
        $ctxMode = $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'mode', $instanceCode);
        $privateKey = ($ctxMode == 'TEST') ? 'private_test_key' : 'private_prod_key';

        return $this->configService->get(GatewayConfiguration::$REST_FIELDS . $privateKey, $instanceCode);
    }

    public function getPublicKey($instanceCode)
    {
        $ctxMode = $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'mode', $instanceCode);
        $publicKey = ($ctxMode == 'TEST') ? 'public_test_key' : 'public_prod_key';

        return $this->configService->get(GatewayConfiguration::$REST_FIELDS . $publicKey, $instanceCode);
    }

    public function getReturnKey($instanceCode)
    {
        $ctxMode = $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'mode', $instanceCode);
        $hmacKey = ($ctxMode == 'TEST') ? 'hmac_test_key' : 'hmac_prod_key';

        return $this->configService->get(GatewayConfiguration::$REST_FIELDS . $hmacKey, $instanceCode);
    }

    public function getToken($order, $instanceCode)
    {
        $params = $this->getRestApiFormTokenData($order, $instanceCode);

        $this->logger->info("Creating form token for order #{$order->getNumber()} with parameters: {$params}");

        try {
            $metadata = "order #{$order->getNumber()}";

            return $this->createFormToken($params, $metadata, $instanceCode);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return false;
        }
    }

    public function convertRestResult($answer): array
    {
        if (! is_array($answer) || empty($answer)) {
            return [];
        }

        $transactions = $this->getProperty($answer, 'transactions');
        if (! is_array($transactions) || empty($transactions)) {
            $transaction = $answer;
        } else {
            $transaction = $transactions[0];
        }

        $response = [];

        $response['vads_result'] = $this->getProperty($transaction, 'errorCode') ? $this->getProperty($transaction, 'errorCode') : '00';
        $response['vads_extra_result'] = $this->getProperty($transaction, 'detailedErrorCode');

        if ($errorMessage = $this->getErrorMessage($transaction)) {
            $response['vads_error_message'] = $errorMessage;
        }

        $response['vads_trans_status'] = $this->getProperty($transaction, 'detailedStatus');
        $response['vads_trans_uuid'] = $this->getProperty($transaction, 'uuid');
        $response['vads_operation_type'] = $this->getProperty($transaction, 'operationType');
        $response['vads_effective_creation_date'] = $this->getProperty($transaction, 'creationDate');
        $response['vads_payment_config'] = 'SINGLE'; // Only single payments are possible via REST API at this time.

        if ($customer = $this->getProperty($answer, 'customer')) {
            $response['vads_cust_email'] = $this->getProperty($customer, 'email');

            if ($billingDetails = $this->getProperty($customer, 'billingDetails')) {
                $response['vads_language'] = $this->getProperty($billingDetails, 'language');
            }
        }

        $response['vads_amount'] = $this->getProperty($transaction, 'amount');
        $response['vads_currency'] = LyraApi::getCurrencyNumCode($this->getProperty($transaction, 'currency'));

        if ($paymentToken = $this->getProperty($transaction, 'paymentMethodToken')) {
            $response['vads_identifier'] = $paymentToken;
            $response['vads_identifier_status'] = 'CREATED';
        }

        if ($orderDetails = $this->getProperty($answer, 'orderDetails')) {
            $response['vads_order_id'] = $this->getProperty($orderDetails, 'orderId');
        }

        $response['vads_order_cycle'] = $this->getProperty($answer, 'orderCycle');

        if (($metadata = $this->getProperty($transaction, 'metadata')) && is_array($metadata)) {
            foreach ($metadata as $key => $value) {
                $response['vads_ext_info_' . $key] = $value;
            }
        }

        if ($transactionDetails = $this->getProperty($transaction, 'transactionDetails')) {
            $response['vads_sequence_number'] = $this->getProperty($transactionDetails, 'sequenceNumber');

            // Workarround to adapt to REST API behavior.
            $effectiveAmount = $this->getProperty($transactionDetails, 'effectiveAmount');
            $effectiveCurrency = LyraApi::getCurrencyNumCode($this->getProperty($transactionDetails, 'effectiveCurrency'));

            if ($effectiveAmount && $effectiveCurrency) {
                // Invert only if there is currency conversion.
                if ($effectiveCurrency !== $response['vads_currency']) {
                    $response['vads_effective_amount'] = $response['vads_amount'];
                    $response['vads_effective_currency'] = $response['vads_currency'];
                    $response['vads_amount'] = $effectiveAmount;
                    $response['vads_currency'] = $effectiveCurrency;
                } else {
                    $response['vads_effective_amount'] = $effectiveAmount;
                    $response['vads_effective_currency'] = $effectiveCurrency;
                }
            }

            $response['vads_warranty_result'] = $this->getProperty($transactionDetails, 'liabilityShift');

            if ($cardDetails = $this->getProperty($transactionDetails, 'cardDetails')) {
                $response['vads_trans_id'] = $this->getProperty($cardDetails, 'legacyTransId'); // Deprecated.
                $response['vads_presentation_date'] = $this->getProperty($cardDetails, 'expectedCaptureDate');

                $response['vads_card_brand'] = $this->getProperty($cardDetails, 'effectiveBrand');
                $response['vads_card_number'] = $this->getProperty($cardDetails, 'pan');
                $response['vads_expiry_month'] = $this->getProperty($cardDetails, 'expiryMonth');
                $response['vads_expiry_year'] = $this->getProperty($cardDetails, 'expiryYear');

                $response['vads_payment_option_code'] = $this->getProperty($cardDetails, 'installmentNumber');


                if ($authorizationResponse = $this->getProperty($cardDetails, 'authorizationResponse')) {
                    $response['vads_auth_result'] = $this->getProperty($authorizationResponse, 'authorizationResult');
                    $response['vads_authorized_amount'] = $this->getProperty($authorizationResponse, 'amount');
                }

                if (($authenticationResponse = self::getProperty($cardDetails, 'authenticationResponse'))
                    && ($value = self::getProperty($authenticationResponse, 'value'))) {
                    $response['vads_threeds_status'] = self::getProperty($value, 'status');
                    $response['vads_threeds_auth_type'] = self::getProperty($value, 'authenticationType');
                    if ($authenticationValue = self::getProperty($value, 'authenticationValue')) {
                        $response['vads_threeds_cavv'] = self::getProperty($authenticationValue, 'value');
                    }
                } elseif (($threeDSResponse = self::getProperty($cardDetails, 'threeDSResponse'))
                    && ($authenticationResultData = self::getProperty($threeDSResponse, 'authenticationResultData'))) {
                    $response['vads_threeds_cavv'] = self::getProperty($authenticationResultData, 'cavv');
                    $response['vads_threeds_status'] = self::getProperty($authenticationResultData, 'status');
                    $response['vads_threeds_auth_type'] = self::getProperty($authenticationResultData, 'threeds_auth_type');
                }
            }

            if ($fraudManagement = $this->getProperty($transactionDetails, 'fraudManagement')) {
                if ($riskControl = $this->getProperty($fraudManagement, 'riskControl')) {
                    $response['vads_risk_control'] = '';

                    foreach ($riskControl as $value) {
                        if (! isset($value['name']) || ! isset($value['result'])) {
                            continue;
                        }

                        $response['vads_risk_control'] .= "{$value['name']}={$value['result']};";
                    }
                }

                if ($riskAssessments = $this->getProperty($fraudManagement, 'riskAssessments')) {
                    $response['vads_risk_assessment_result'] = $this->getProperty($riskAssessments, 'results');
                }
            }
        }

        return $response;
    }

    public function getAccountToken($customer, $currency, $instanceCode)
    {
        $address = $customer->getAddresses()[0] ?? null;

        $params = array(
            'formAction' => 'CUSTOMER_WALLET',
            'customer' => array(
                'email' => $customer->getEmail(),
                'reference' => $customer->getId(),
                'billingDetails' => array(
                    'firstName' => $customer->getFirstName(),
                    'lastName' => $customer->getLastName()
                )
            ),
            'contrib' => Tools::getContrib(),
            'currency' => $currency,
            'metadata' => array(
                'from_account' => true
            )
        );

        if ($address !== null) {
            $params['customer']['billingDetails']['address'] = $address->getStreet();
            $params['customer']['billingDetails']['zipCode'] = $address->getPostcode();
            $params['customer']['billingDetails']['city'] = $address->getCity();
            $params['customer']['billingDetails']['country'] = $address->getCountryCode();

            if ($state = $address->getProvinceName()) {
                $params['customer']['billingDetails']['state'] = $state;
            }
        }

        return $this->createFormToken(json_encode($params), "user {$customer->getEmail()}", $instanceCode, 'CreateToken');
    }

    public static function checkRestResponseValidity($request): bool
    {
        return $request->get('kr-hash') !== null && $request->get('kr-hash-algorithm') !== null && $request->get('kr-answer') !== null;
    }

    public function checkResponseHash($data, $key): bool
    {
        $supportedSignAlgos = array('sha256_hmac');

        // Check if the hash algorithm is supported.
        if (! in_array($data->get('kr-hash-algorithm'), $supportedSignAlgos)) {
            $this->logger->error('Hash algorithm is not supported: ' . $data->get('kr-hash-algorithm'));

            return false;
        }

        // On some servers / can be escaped.
        $krAnswer = str_replace('\/', '/', $data->get('kr-answer') ?: '');

        $hash = hash_hmac('sha256', $krAnswer, $key);

        // Return true if calculated hash and sent hash are the same.
        return ($hash === $data->get('kr-hash'));
    }

    private function getRestApiFormTokenData($order, $instanceCode)
    {
        if (! $order || ! $order->getNumber()) {
            $this->logger->error('Cannot create a form token. Empty cart passed');

            return false;
        }

        $amount = $order->getTotal();
        if ($amount <= 0) {
            $this->logger->error('Cannot create a form token. Invalid amount passed.');

            return false;
        }

        $currency = LyraApi::findCurrencyByAlphaCode($order->getCurrencyCode());
        if (! $currency) {
            $this->logger->error('Cannot create a form token. Unsupported currency passed.');

            return false;
        }

        $request = $this->prepareRequest($order);

        $data = [
            'orderId' => $request->get('order_id'),
            'customer' => [
                'email' => $request->get('cust_email'),
                'reference' => $request->get('cust_id'),
                'billingDetails' => [
                    'language' => $request->get('language'),
                    'title' => $request->get('cust_title'),
                    'firstName' => $request->get('cust_first_name'),
                    'lastName' => $request->get('cust_last_name'),
                    'address' => $request->get('cust_address'),
                    'zipCode' => $request->get('cust_zipcode'),
                    'city' => $request->get('cust_city'),
                    'phoneNumber' => $request->get('cust_phone'),
                    'cellPhoneNumber' => $request->get('cust_cell_phone'),
                    'country' => $request->get('cust_country')
                ]
            ],
            'transactionOptions' => [
                'cardOptions' => [
                    'paymentSource' => 'EC'
                ]
            ],
            'contrib' => $request->get('contrib'),
            'currency' => $request->get('currency'),
            'amount' => $request->get('amount'),
            'metadata' => [
                'db_order_id'=> $order->getId(),
                'db_payment_id' => $order->getLastPayment()->getId(),
                'db_method_code' => $instanceCode
            ]
        ];

        // In case of Smartform, only payment means supporting capture delay will be shown.
        $captureDelay = $this->configService->get(GatewayConfiguration::$PAYMENT_OPTIONS . 'capture_delay', $instanceCode);
        if (is_numeric($captureDelay)) {
            $data['transactionOptions']['cardOptions']['capture_delay'] = $captureDelay;
        }

        // Set Number of attempts in case of rejected payment.
        $restAttempts = $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'rest_attempts', $instanceCode);
        if (is_numeric($restAttempts)) {
            $data['transactionOptions']['cardOptions']['retry'] = $restAttempts;
        }

        $validationMode = $this->configService->get(GatewayConfiguration::$PAYMENT_OPTIONS . 'validation_mode', $instanceCode);
        if (! is_null($validationMode)) {
            $data['transactionOptions']['cardOptions']['manualValidation'] = ($validationMode === '1') ? 'YES' : 'NO';
        }

        // Set shipping info.
        if ($order->getShippingAddress()) {
            $data['customer']['shippingDetails'] = [
                'firstName' => $request->get('ship_to_first_name'),
                'lastName' => $request->get('ship_to_last_name'),
                'address' => $request->get('ship_to_street'),
                'zipCode' => $request->get('ship_to_zip'),
                'city' => $request->get('ship_to_city'),
                'state' => $request->get('ship_to_state'),
                'country' => $request->get('ship_to_country')
            ];
        }

        $state = $order->getBillingAddress()->getProvinceCode();
        if (! empty($state)) {
            $data['customer']['billingDetails']['state'] = $state;
        }

        $state = $order->getShippingAddress()->getProvinceCode();
        if (! empty($state)) {
            $data['customer']['shippingDetails']['state'] = $state;
        }

        $customer = $this->customerRepository->findOneBy(['id' => $request->get('cust_id')]);
        if ($this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'oneclick_payment', $instanceCode) && $customer->getUser() !== null){
            $data['formAction'] = 'CUSTOMER_WALLET';
        }

        return json_encode($data);
    }

    private function prepareRequest($order)
    {
        $request = new LyraRequest();

        // Get the shop language code.
        $lang = $order->getLocaleCode();

        // Retrieve amount.
        $total = $order->getTotal();
        $lyraCurrency = LyraApi::findCurrencyByAlphaCode($order->getCurrencyCode());

        $customer = $order->getCustomer();
        $billingAddress = $order->getBillingAddress();

        // Other parameters.
        $data = [
            // Order info.
            'amount' => $total,
            'order_id' => $order->getNumber(),
            'contrib' => Tools::getContrib(),

            // Misc data.
            'currency' => $lyraCurrency->getAlpha3(),
            'language' => $lang,
            'url_return' => $this->router->generate('lyra_return_url', [], UrlGenerator::ABSOLUTE_URL),

            // Customer info.
            'cust_id' => $customer->getId(),
            'cust_email' => $customer->getEmail(),
            'cust_phone' => $customer->getPhoneNumber(),
            'cust_cell_phone' => $customer->getPhoneNumber(),
            'cust_first_name' => $customer->getFirstName(),
            'cust_last_name' => $customer->getLastName(),
            'cust_address' => $billingAddress->getStreet(),
            'cust_city' => $billingAddress->getCity(),
            'cust_state' => $billingAddress->getProvinceCode(),
            'cust_zip' => $billingAddress->getPostcode(),
            'cust_country' => $billingAddress->getCountryCode(),
        ];

        // Delivery data.
        $shippingAddress = $order->getBillingAddress();
        if ($shippingAddress) {
            $data['ship_to_first_name'] = $shippingAddress->getFirstName();
            $data['ship_to_last_name'] = $shippingAddress->getLastName();
            $data['ship_to_street'] = $shippingAddress->getStreet();
            $data['ship_to_city'] = $shippingAddress->getCity();
            $data['ship_to_state'] = $shippingAddress->getProvinceCode();
            $data['ship_to_country'] = $shippingAddress->getCountryCode();
            $data['ship_to_zip'] =$shippingAddress->getPostcode();
        }

        $request->setFromArray($data);

        return $request;
    }

    private function getProperty($restResult, $key)
    {
        if (isset($restResult[$key])) {
            return $restResult[$key];
        }

        return null;
    }

    private function getErrorMessage($transaction)
    {
        $code = $this->getProperty($transaction, 'errorCode');
        if ($code) {
            return ucfirst($this->getProperty($transaction, 'errorMessage')) . ' (' . $code . ').';
        }

        return null;
    }

    private function createFormToken($params, $metadata, $instanceCode, $webService = 'CreatePayment')
    {
        // Perform our request.
        $client = new LyraRest(
            LyraTools::getDefault('REST_URL'),
            $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'site_id', $instanceCode),
            $this->getPrivateKey($instanceCode)
        );

        $response = $client->post('V4/Charge/' . $webService, $params);

        if ($response['status'] !== 'SUCCESS') {
            $msg = "Error while creating form token for {$metadata}: " . $response['answer']['errorMessage'] . ' (' . $response['answer']['errorCode'] . ').';

            if (! empty($response['answer']['detailedErrorMessage'])) {
                $msg .= ' Detailed message: ' . $response['answer']['detailedErrorMessage'] . ' (' . $response['answer']['detailedErrorCode'] . ').';
            }

            $this->logger->error($msg);

            return false;
        } else {
            $this->logger->info("Form token created successfully for {$metadata}.");

            return $response['answer']['formToken'];
        }
    }
}