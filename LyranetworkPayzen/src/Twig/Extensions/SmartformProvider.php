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
namespace Lyranetwork\Payzen\Twig\Extensions;

use Lyranetwork\Payzen\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Payzen\Sdk\RestData;
use Lyranetwork\Payzen\Sdk\Tools as PayzenTools;
use Lyranetwork\Payzen\Service\ConfigService;
use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;
use Lyranetwork\Payzen\Service\OrderService;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use SM\Factory\FactoryInterface;

use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Locale\Context\LocaleContextInterface;

use Psr\Log\LoggerInterface;

class SmartformProvider extends AbstractExtension
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var RestData
     */
    private $restData;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var FactoryInterface
     */
    private $stateMachineFactory;

    /**
     * @var LocaleContextInterface
     */
    private  $localeContext;

    public function __construct(
        LoggerInterface $logger,
        ConfigService $configService,
        RestData $restData,
        OrderService $orderService,
        FactoryInterface $stateMachineFactory,
        LocaleContextInterface $localeContext
    )
    {
        $this->logger = $logger;
        $this->configService = $configService;
        $this->restData = $restData;
        $this->orderService = $orderService;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->localeContext = $localeContext;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('payzen_get_smartform_config', [$this, 'getSmartformConfig'], ['is_safe' => ['html']]),
            new TwigFunction('payzen_get_smartform_token', [$this, 'getSmartformToken'], ['is_safe' => ['html']]),
        ];
    }

    public function getSmartformToken($order, $instanceCode): array
    {
        $orderStateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);
        if ($orderStateMachine->can('create')) {
            $orderStateMachine->apply('create');
        }

        $order = $this->orderService->get(strval($order->getId()));

        $token = $this->restData->getToken($order, $instanceCode);

        return [
            'formToken' => $token
        ];
    }

    public function getSmartformConfig($instanceCode): array
    {
        $cardDataEntryMode = $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'card_data_entry_mode', $instanceCode);
        $popinMode = $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'rest_popin_mode', $instanceCode);
        $theme = $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'rest_theme', $instanceCode);
        $compactMode = $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'rest_compact_mode', $instanceCode);
        $jsClient = PayzenTools::getDefault('STATIC_URL');
        $pubKey = $this->restData->getPublicKey($instanceCode);
        $language = substr($this->localeContext->getLocaleCode(), 0, 2);

        return [
            'cardDataEntryMode' => $cardDataEntryMode,
            'popinMode' => $popinMode,
            'theme' => strtolower($theme),
            'compactMode' => $compactMode,
            'jsClient' => $jsClient,
            'pubKey' => $pubKey,
            'language' => $language,
        ];
    }
}