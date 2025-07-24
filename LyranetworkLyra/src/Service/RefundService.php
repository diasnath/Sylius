<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

namespace Lyranetwork\Lyra\Service;

use Lyranetwork\Lyra\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Lyra\Sdk\RefundProcessor as LyraRefundProcessor;
use Lyranetwork\Lyra\Sdk\RestData;
use Lyranetwork\Lyra\Sdk\Tools as LyraTools;
use Lyranetwork\Lyra\Sdk\Refund\Api as LyraRefund;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;
use Lyranetwork\Lyra\Sdk\Refund\OrderInfo as LyraOrderInfo;

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
     * @var LyraRefundProcessor
     */
    private $refundProcessor;

    public function __construct(
        RestData $restData,
        ConfigService $configService,
        LyraRefundProcessor $refundProcessor
    ) {
        $this->restData = $restData;
        $this->configService = $configService;
        $this->refundProcessor = $refundProcessor;
    }

    public function refund($paymentMethodCode, $order, $userInfo, $amount)
    {
        $lyraOrderInfo = new LyraOrderInfo();
        $lyraOrderInfo->setOrderRemoteId($order->getNumber());
        $lyraOrderInfo->setOrderId($order->getNumber());
        $lyraOrderInfo->setOrderReference($order->getNumber());
        $lyraOrderInfo->setOrderCurrencyIsoCode($order->getCurrencyCode());
        $lyraOrderInfo->setOrderCurrencySign($order->getCurrencyCode());
        $lyraOrderInfo->setOrderUserInfo($userInfo);

        $refundApi = new LyraRefund(
            $this->refundProcessor->getProcessor(),
            $this->restData->getPrivateKey($paymentMethodCode),
            LyraTools::getDefault('REST_URL'),
            $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'site_id', $paymentMethodCode),
            'Sylius'
        );

        return $refundApi->refund($lyraOrderInfo, $amount);
    }
}