<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace Lyranetwork\Payzen\Controller;

use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\OrderBundle\Controller\OrderController as BaseOrderController;
use Sylius\Bundle\ResourceBundle\Controller\AuthorizationCheckerInterface;
use Sylius\Bundle\ResourceBundle\Controller\EventDispatcherInterface;
use Sylius\Bundle\ResourceBundle\Controller\FlashHelperInterface;
use Sylius\Bundle\ResourceBundle\Controller\NewResourceFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\RedirectHandlerInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceDeleteHandlerInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceFormFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourcesCollectionProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceUpdateHandlerInterface;
use Sylius\Bundle\ResourceBundle\Controller\SingleResourceProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\StateMachineInterface;
use Sylius\Bundle\ResourceBundle\Controller\ViewHandlerInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\ResourceActions;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Calendar\Provider\DateTimeProviderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RouterInterface;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

use Psr\Log\LoggerInterface;

use Lyranetwork\Payzen\Sdk\Tools;
use Lyranetwork\Payzen\Sdk\RestData;
use Lyranetwork\Payzen\Sdk\Form\Response as PayzenResponse;
use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;
use Lyranetwork\Payzen\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Payzen\Service\ConfigService;
use Lyranetwork\Payzen\Service\OrderService;
use Lyranetwork\Payzen\Repository\PaymentMethodRepositoryInterface;

final class OrderController extends BaseOrderController
{
    /**
     * @var LoggerInterface
     */
    private  $logger;

    /**
     * @var RestData
     */
    private $restData;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var PaymentMethodRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var LocaleContextInterface
     */
    private  $localeContext;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Sylius\Abstraction\StateMachine\StateMachineInterface;
     */
    private $stateMachineInterface;

    public function __construct(
        MetadataInterface $metadata,
        RequestConfigurationFactoryInterface $requestConfigurationFactory,
        ?ViewHandlerInterface $viewHandler,
        RepositoryInterface $repository,
        FactoryInterface $factory,
        NewResourceFactoryInterface $newResourceFactory,
        ObjectManager $manager,
        SingleResourceProviderInterface $singleResourceProvider,
        ResourcesCollectionProviderInterface $resourcesFinder,
        ResourceFormFactoryInterface $resourceFormFactory,
        RedirectHandlerInterface $redirectHandler,
        FlashHelperInterface $flashHelper,
        AuthorizationCheckerInterface $authorizationChecker,
        EventDispatcherInterface $eventDispatcher,
        ?StateMachineInterface $stateMachine,
        ResourceUpdateHandlerInterface $resourceUpdateHandler,
        ResourceDeleteHandlerInterface $resourceDeleteHandler,
        LoggerInterface $logger,
        RestData $restData,
        ConfigService $configService,
        OrderService $orderService,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        TranslatorInterface $translator,
        LocaleContextInterface $localeContext,
        RouterInterface $router,
        \Sylius\Abstraction\StateMachine\StateMachineInterface $stateMachineInterface
    ) {
        parent::__construct(
            $metadata,
            $requestConfigurationFactory,
            $viewHandler,
            $repository,
            $factory,
            $newResourceFactory,
            $manager,
            $singleResourceProvider,
            $resourcesFinder,
            $resourceFormFactory,
            $redirectHandler,
            $flashHelper,
            $authorizationChecker,
            $eventDispatcher,
            $stateMachine,
            $resourceUpdateHandler,
            $resourceDeleteHandler
        );
        $this->logger = $logger;
        $this->restData = $restData;
        $this->configService = $configService;
        $this->orderService = $orderService;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->translator = $translator;
        $this->localeContext = $localeContext;
        $this->router = $router;
        $this->stateMachineInterface = $stateMachineInterface;
    }

    public function paymentResponseAction(Request $request)
    {
        $fromServer = (! empty($request->get("kr-hash-key"))) && ($request->get("kr-hash-key") !== "sha256_hmac");
        $headlessMode = $request->getRequestUri() === "/payzen/rest/headless/return";

        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);
        $this->isGrantedOr403($configuration, ResourceActions::UPDATE);

        // Check the validity of the request.
        if (! $this->restData->checkRestResponseValidity($request)) {
            $this->logger->error('Invalid response received. Content: ' . json_encode($request));
            if ($fromServer) {
                die('<span style="display:none">KO-Invalid IPN request received.' . "\n" . '</span>');
            } else if ($headlessMode) {
                return $this->json(["errorCode" => "500", "errorMessage" => "Invalid response received."], 500);
            } else {
                $redirect = $this->redirectToRoute('sylius_shop_checkout_select_payment', ['_locale' => $request->getDefaultLocale()]);

                return new RedirectResponse($redirect->getTargetUrl());
            }
        }

        $answer = json_decode($request->get('kr-answer'), true);
        if (! is_array($answer) || empty($answer)) {
            $this->logger->error('Invalid response received. Content of kr-answer: ' . json_encode($request->get('kr-answer')));
            if ($fromServer) {
                die('<span style="display:none">KO-Invalid IPN request received.' . "\n" . '</span>');
            } else if ($headlessMode) {
                return $this->json(["errorCode" => "500", "errorMessage" => "Invalid response received."], 500);
            } else {
                $redirect = $this->redirectToRoute('sylius_shop_checkout_select_payment', ['_locale' => $request->getDefaultLocale()]);

                return new RedirectResponse($redirect->getTargetUrl());
            }
        }

        $params = $this->restData->convertRestResult(json_decode($request->get('kr-answer'), true));
        $payzenResponse = new PayzenResponse(
            $params,
            "",
            "",
            ""
        );

        $orderId = $payzenResponse->get('order_id');
        $orderIdDB = $payzenResponse->getExtInfo('db_order_id');

        // Ignore IPN call for adding card in the customer wallet.
        if ($payzenResponse->get('operation_type') === 'VERIFICATION') {
            die();
        }

        // Ignore IPN on cancelation for already registered orders.
        if (($payzenResponse->getTransStatus() === 'ABANDONED') ||
            (($payzenResponse->getTransStatus() === 'CANCELLED')
                && ((($payzenResponse->get('order_status') === 'UNPAID') && ($payzenResponse->get('order_cycle') === 'CLOSED')) || ($payzenResponse->get('url_check_src') !== 'MERCH_BO')))) {
            $this->logger->warn('Server call on cancellation for order #' . $orderId . '. No order will be updated.');

            die('<span style="display:none">KO-Payment abandoned.' . "\n" . '</span>');
        }

        if (empty($orderIdDB) || empty($orderId) || ! ($order = $this->orderService->get($orderIdDB))) {
            $this->logger->error("Order #$orderId not found in database.");
            if ($fromServer) {
                die($payzenResponse->getOutputForGateway('order_not_found'));
            } else if ($headlessMode) {
                return $this->json(["errorCode" => "500", "errorMessage" => "Order #$orderId was not found in database."], 500);
            } else {
                $redirect = $this->redirectToRoute('sylius_shop_checkout_select_payment', ['_locale' => $request->getDefaultLocale()]);

                return new RedirectResponse($redirect->getTargetUrl());
            }
        }

        $instanceCode = $payzenResponse->getExtInfo('db_method_code');
        $key = $fromServer ? $this->restData->getPrivateKey($instanceCode) : $this->restData->getReturnKey($instanceCode);
        if (! $this->restData->checkResponseHash($request, $key)) {
            $this->logger->error("Tried to access payzen/rest/ipn page without valid signature.");
            $this->logger->error('Signature algorithm selected in module settings must be the same as one selected in PayZen Back Office.');
            if ($fromServer) {
                die($payzenResponse->getOutputForGateway('auth_fail'));
            } else if ($headlessMode) {
                return $this->json(["errorCode" => "500", "errorMessage" => $payzenResponse->getOutputForGateway('auth_fail')], 500);
            } else {
                $request->getSession()->getFlashBag()->add('error', $this->translator->trans('sylius_payzen_plugin.payment.fatal', locale: $order->getLocaleCode()));
                $redirect = $this->redirectToRoute('sylius_shop_checkout_select_payment', ['_locale' => $order->getLocaleCode()]);

                return new RedirectResponse($redirect->getTargetUrl());
            }
        }

        if ($fromServer) {
            $this->logger->info("Server call process starts for order #$orderId.");
        } else if ($headlessMode) {
            $this->logger->info("Headless return call process starts for order #$orderId.");
        } else {
            $this->logger->info("Return call process starts for order #$orderId.");
        }

        $lastPayment = $order->getLastPayment();
        $payments = $order->getPayments();
        $cpt = count($payments);

        while ($cpt > 0 && ! empty($lastPayment) && $lastPayment->getState() === 'refunded') {
            $lastPayment = $payments[$cpt - 1];
            $cpt--;
        }

        if (! $payzenResponse->isAcceptedPayment()) {
            $lastPayment = ! empty($payments[count($payments) - 2]) ? $payments[count($payments) - 2] : $lastPayment;
            $redirect = $this->redirectToRoute('sylius_shop_order_show', ['tokenValue' => $order->getTokenValue(), '_locale' => $order->getLocaleCode()]);
        } else {
            $redirect = $this->redirectToRoute('sylius_shop_order_thank_you', ['_locale' => $order->getLocaleCode()]);
        }

        $request->getSession()->set('sylius_order_id', $order->getId());
        $amount = $lastPayment->getAmount();

        $lastPayment->setMethod($this->paymentMethodRepository->findByGatewayNameAndCode(Tools::FACTORY_NAME, $instanceCode));
        $details = array(
            'payzen_factory_name' => Tools::FACTORY_NAME,
            'payzen_trans_id' => $payzenResponse->get('trans_id'),
            'payzen_trans_uuid' => $payzenResponse->get('trans_uuid'),
            'payzen_card_brand' => $payzenResponse->get('vads_card_brand'),
            'payzen_payment_initial_amount' => $amount
        );

        $initialAmount = $lastPayment->getDetails() && $lastPayment->getDetails()['payzen_payment_initial_amount'] ? $lastPayment->getDetails()['payzen_payment_initial_amount'] : $amount;

        if ($payzenResponse->get('vads_amount') != $amount) {
            if ($payzenResponse->get('operation_type') === 'DEBIT') {
                if ($payzenResponse->get('vads_amount') < $amount) {
                    $lastPayment->setAmount($payzenResponse->get('vads_amount'));
                }

                $this->eventDispatcher->dispatchPostEvent(ResourceActions::UPDATE, $configuration, $lastPayment);
            } else {
                $refundedPayment = $this->container->get('sylius.factory.payment')->createNew();
                $refundedPayment->setAmount($payzenResponse->get('vads_amount'));
                $refundedPayment->setDetails($details);
                $refundedPayment->setMethod($this->paymentMethodRepository->findByGatewayNameAndCode(Tools::FACTORY_NAME, $instanceCode));
                $refundedPayment->setCurrencyCode($lastPayment->getCurrencyCode());
                $order->addPayment($refundedPayment);

                $this->stateMachineInterface->apply($refundedPayment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CREATE);
                $this->stateMachineInterface->apply($refundedPayment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);
                $this->stateMachineInterface->apply($refundedPayment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
            }

            $this->manager->flush();
        }

        $msg = "";
        $oldStatus = $lastPayment->getState();
        $newStatus = $this->getNewStatus($payzenResponse, $oldStatus, $amount, $initialAmount);

        if ($oldStatus !== $newStatus['payment'] || $order->getPaymentState() !== $newStatus['orderPayment']) {
            $lastPayment = $order->getLastPayment();

            if ($this->stateMachineInterface->can($lastPayment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CREATE)) {
                $this->stateMachineInterface->apply($lastPayment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CREATE);
            }

            if (! $fromServer) {
                $request->getSession()->getFlashBag()->add('warning', $this->translator->trans('sylius_payzen_plugin.payment.check_url_warn',  locale: $order->getLocaleCode()));
            }

            if ($payzenResponse->isPendingPayment()) {
                $this->logger->info("Payment pending for order #$orderId. New payment status: " . $newStatus['payment']);
                if ($this->stateMachineInterface->can($lastPayment, PaymentTransitions::GRAPH, $newStatus['paymentTransition'])) {
                    if ($fromServer) {
                        $msg = 'payment_ok';
                    }

                    $this->stateMachineInterface->apply($lastPayment, PaymentTransitions::GRAPH, $newStatus['paymentTransition']);
                    $this->logger->info("Pending payment processed successfully for order #$orderId.");
                } else {
                    $this->logger->info("Payment pending processing failed for order #$orderId.");
                }

                $lastPayment->setDetails($details);
            } elseif ($payzenResponse->isAcceptedPayment()) {
                $this->logger->info("Payment accepted for order #$orderId. New order status: " . $newStatus['orderPayment'] . " , new payment status: " . $newStatus['payment']);
                if ($fromServer) {
                    $msg = 'payment_ok';
                }

                if ($this->stateMachineInterface->can($order, OrderPaymentTransitions::GRAPH, $newStatus['orderPaymentTransition'])) {
                    $this->stateMachineInterface->apply($order, OrderPaymentTransitions::GRAPH, $newStatus['orderPaymentTransition']);

                    $this->logger->info("Order payment status processed successfully for order #$orderId.");
                } else {
                    $this->logger->info("Payment accepted, order payment status processing failed for order #$orderId.");
                }

                if ($this->stateMachineInterface->can($lastPayment, PaymentTransitions::GRAPH, $newStatus['paymentTransition'])) {
                    $this->stateMachineInterface->apply($lastPayment, PaymentTransitions::GRAPH, $newStatus['paymentTransition']);

                    $this->logger->info("Payment status processed successfully for order #$orderId.");
                } else {
                    $this->logger->info("Payment accepted, payment status processing failed for order #$orderId.");
                }

                $lastPayment->setDetails($details);
            } else {
                $this->logger->info("Payment failed or cancelled for order #$orderId. {$payzenResponse->getLogMessage()}");
                if ($fromServer) {
                    $this->logger->info("Payment processed successfully by IPN URL call.");
                    $msg = 'payment_ko';
                }

                if ($payzenResponse->get('order_cycle') === 'CLOSED') {
                    if ($this->stateMachineInterface->can($lastPayment, PaymentTransitions::GRAPH, $newStatus['paymentTransition'])) {
                        $this->stateMachineInterface->apply($lastPayment, PaymentTransitions::GRAPH, $newStatus['paymentTransition']);
                    }

                    if (isset($newStatus['orderPaymentTransition']) && $this->stateMachineInterface->can($order, OrderPaymentTransitions::GRAPH, $newStatus['orderPaymentTransition'])) {
                        $this->stateMachineInterface->apply($order, OrderPaymentTransitions::GRAPH, $newStatus['orderPaymentTransition']);
                    }

                    if ($this->stateMachineInterface->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING)) {
                        $this->stateMachineInterface->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
                    }

                    $lastPayment->setDetails($details);

                    // Update the lastPayment to let the buyer retry.
                    $lastPayment = $order->getLastPayment();
                    if ($this->stateMachineInterface->can($lastPayment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CREATE)) {
                        $this->stateMachineInterface->apply($lastPayment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CREATE);
                    }
                }
            }
        } else {
            $this->logger->info("Order #$orderId is already saved.");
            if ($fromServer) {
                $this->logger->info("IPN URL process end for order #$orderId.");
                $ipnMsg = $payzenResponse->getOutputForGateway('payment_ok_already_done');

                die($ipnMsg);
            } else if ($headlessMode) {
                $this->logger->info("Headless URL process end for order #$orderId.");

                return $this->json(["orderId" => $orderId, "orderPaymentState" => $newStatus["payment"]], 200);
            }
        }

        $this->eventDispatcher->dispatchPostEvent(ResourceActions::UPDATE, $configuration, $order);
        $this->manager->flush();

        if ($fromServer) {
            $this->logger->info("IPN URL process end for order #$orderId.");
            $ipnMsg = $payzenResponse->getOutputForGateway($msg);

            die($ipnMsg);
        } else if ($headlessMode) {
            $this->logger->info("Headless URL process end for order #$orderId.");

            return $this->json(["orderId" => $orderId, "orderPaymentState" => $newStatus["payment"]], 200);
        }

        $this->logger->info("Return URL process end for order #$orderId.");

        if ($this->configService->get(GatewayConfiguration::$REST_FIELDS . 'mode', $instanceCode) === 'TEST' && Tools::$pluginFeatures['prodfaq']) {
            $request->getSession()->getFlashBag()->add('info', $this->translator->trans('sylius_payzen_plugin.payment.prodfaq', locale: $order->getLocaleCode()));
        }

        return new RedirectResponse($redirect->getTargetUrl());
    }

    public function getFormToken(Request $request)
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);
        $this->isGrantedOr403($configuration, ResourceActions::UPDATE);

        $instanceCode = $request->get('instanceCode');
        $tokenValue = $request->get('tokenValue');
        $orderIdDB = $request->get('orderIdDB');
        if (! $instanceCode || (! $tokenValue && ! $orderIdDB)) {
            return $this->json(['errorMessage' => 'Invalid request received.', 'errorCode' => '500'], 500);
        }

        $msg = '';
        $order = null;
        if ($tokenValue && ! $order = $this->orderService->getByTokenValue($tokenValue)) {
            $msg = "Order not found in database for token value {$tokenValue} and instance [{$instanceCode}].";
        }

        if (! $order && $orderIdDB && ! $order = $this->orderService->get($orderIdDB)) {
            $msg = "Order not found in database for orderId {$orderIdDB} and instance [{$instanceCode}].";
        }

        if (! $order) {
            return $this->json(['errorMessage' => $msg, 'errorCode' => '500'], 500);
        }

        if ($this->stateMachineInterface->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT)) {
            $this->stateMachineInterface->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        }

        if ($this->stateMachineInterface->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachineInterface->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        }

        if ($this->stateMachineInterface->can($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CREATE)) {
            $this->stateMachine->apply($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CREATE);
        }

        $this->eventDispatcher->dispatchPostEvent(ResourceActions::UPDATE, $configuration, $order);
        $this->manager->flush();

        $formToken = $this->restData->getToken($order, $instanceCode);
        $restPublicKey = $this->restData->getPublicKey($instanceCode);
        $popin = $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'rest_popin_mode', $instanceCode);
        $theme = $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'rest_theme', $instanceCode);
        $compact = $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'rest_compact_mode', $instanceCode);
        $language = substr($this->localeContext->getLocaleCode(), 0, 2);
        $returnUrl = $this->router->generate('payzen_headless_return_url', [], UrlGenerator::ABSOLUTE_URL);
        $redirectOnClose = $this->redirectToRoute('sylius_shop_order_show', ['tokenValue' => $order->getTokenValue(), '_locale' => $order->getLocaleCode()])->getTargetUrl();
        $cardDataEntryMode = $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'card_data_entry_mode', $instanceCode);
        $formExpanded = ($cardDataEntryMode !== 'MODE_SMARTFORM');
        $cardLogoHeader = ($cardDataEntryMode === 'MODE_SMARTFORM_EXT_WITHOUT_LOGOS');

        $informations = [
            'formToken' => $formToken,
            'restPublicKey' => $restPublicKey,
            'smartformConfig' => [
                'theme' => $theme,
                'popin' => $popin,
                'compact' => $compact,
                'form_expanded' => $formExpanded,
                'card_logo_header' => $cardLogoHeader,
                'language' => $language,
                'url_success' => $returnUrl,
                'url_refused' => $returnUrl,
                'js_client_url' => Tools::getDefault('STATIC_URL')
            ],
            'redirectOnClose' => $redirectOnClose
        ];

        return $this->json($informations);
    }

    private function getNewStatus($payzenResponse, $oldStatus, $amount, $initialAmount): array
    {
        $newStatus = array('orderPayment' => "awaiting_payment");
        if ($payzenResponse->isPendingPayment()) {
            $newStatus['payment'] = 'processing';
            $newStatus['paymentTransition'] = 'process';
        } elseif ($payzenResponse->isAcceptedPayment()) {
            $newStatus['orderPayment'] = 'paid';
            $newStatus['payment'] = 'completed';
            $newStatus['orderPaymentTransition'] = 'pay';
            $newStatus['paymentTransition'] = 'complete';

            if ($payzenResponse->get('vads_amount') < $amount) {
                if ($oldStatus === 'new') {
                    $newStatus['orderPayment'] = 'partially_paid';
                    $newStatus['orderPaymentTransition'] = 'partially_pay';
                } else {
                    $newStatus['orderPayment'] = 'partially_refunded';
                    $newStatus['orderPaymentTransition'] = 'partially_refund';
                }
            } else if ($payzenResponse->get('vads_amount') === $amount && $payzenResponse->get('operation_type') === 'CREDIT') {
                $newStatus['orderPayment'] = 'refunded';
                $newStatus['payment'] = 'refunded';
                $newStatus['orderPaymentTransition'] = 'refund';
                $newStatus['paymentTransition'] = 'refund';
            } else if ($payzenResponse->get('vads_amount') === $amount && $payzenResponse->get('vads_amount') < $initialAmount) {
                $newStatus['orderPayment'] = 'partially_paid';
                $newStatus['orderPaymentTransition'] = 'partially_pay';
            }
        } else {
            if ($payzenResponse->isCancelledPayment()) {
                $newStatus['payment'] = 'cancelled';
                $newStatus['paymentTransition'] = 'cancel';

                if ($oldStatus === 'completed') {
                    $newStatus['orderPayment'] = 'refunded';
                    $newStatus['payment'] = 'refunded';
                    $newStatus['orderPaymentTransition'] = 'refund';
                    $newStatus['paymentTransition'] = 'refund';
                }
            } else {
                $newStatus['payment'] = 'failed';
                $newStatus['paymentTransition'] = 'fail';
            }
        }

        return $newStatus;
    }
}