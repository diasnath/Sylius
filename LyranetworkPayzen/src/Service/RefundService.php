<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

namespace Lyranetwork\Payzen\Service;

use Lyranetwork\Payzen\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Payzen\Sdk\RefundProcessor as PayzenRefundProcessor;
use Lyranetwork\Payzen\Sdk\RestData;
use Lyranetwork\Payzen\Sdk\Tools as PayzenTools;
use Lyranetwork\Payzen\Sdk\Refund\Api as PayzenRefund;
use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;
use Lyranetwork\Payzen\Sdk\Refund\OrderInfo as PayzenOrderInfo;

class RefundService
{
    /**
    * @var RestData
    */
    private $restData;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var PayzenRefundProcessor
     */
    private $refundProcessor;

    public function __construct(
        RestData $restData,
        ConfigService $configService,
        PayzenRefundProcessor $refundProcessor
    ) {
        $this->restData = $restData;
        $this->configService = $configService;
        $this->refundProcessor = $refundProcessor;
    }

    public function refund($paymentMethodCode, $order, $userInfo, $amount)
    {
        $payzenOrderInfo = new PayzenOrderInfo();
        $payzenOrderInfo->setOrderRemoteId($order->getNumber());
        $payzenOrderInfo->setOrderId($order->getNumber());
        $payzenOrderInfo->setOrderReference($order->getNumber());
        $payzenOrderInfo->setOrderCurrencyIsoCode($order->getCurrencyCode());
        $payzenOrderInfo->setOrderCurrencySign($order->getCurrencyCode());
        $payzenOrderInfo->setOrderUserInfo($userInfo);

        $refundApi = new PayzenRefund(
            $this->refundProcessor->getProcessor(),
            $this->restData->getPrivateKey($paymentMethodCode),
            PayzenTools::getDefault('REST_URL'),
            $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'site_id', $paymentMethodCode),
            'Sylius'
        );

        return $refundApi->refund($payzenOrderInfo, $amount);
    }
}