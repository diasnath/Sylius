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

namespace Lyranetwork\Payzen\EventListener;

use Lyranetwork\Payzen\Repository\PaymentMethodRepositoryInterface;
use Lyranetwork\Payzen\Service\ConfigService;
use Lyranetwork\Payzen\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final class AccountMenuListener
{
    /**
     * @var PaymentMethodRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var ChannelContextInterface
     */
    private $channelContext;

    public function __construct(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        ConfigService $configService,
        ChannelContextInterface $channelContext
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->configService = $configService;
        $this->channelContext = $channelContext;
    }

    public function addAccountMenuItems(MenuBuilderEvent $event): void
    {
        $gatewayName = constant('Lyranetwork\Payzen\Payum\SyliusPaymentGatewayFactory::FACTORY_NAME');
        $payzenPaymentMethods = $this->paymentMethodRepository->findAllByGatewayName($gatewayName);

        if (is_array($payzenPaymentMethods) && ! empty($payzenPaymentMethods)) {
            foreach ($payzenPaymentMethods as $payzenPaymentMethod) {
                if ($payzenPaymentMethod->isEnabled()
                    && $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'oneclick_payment', $payzenPaymentMethod->getCode())
                    && $payzenPaymentMethod->hasChannel($this->channelContext->getChannel())
                ) {
                    $menu = $event->getMenu();

                    $menu->addChild('card', ['route' => 'payzen_sylius_account_saved_cards'])
                        ->setAttribute('type', 'link')
                        ->setLabel('sylius_payzen_plugin.ui.account.saved_cards.title')
                        ->setLabelAttribute('icon', 'credit card');

                    break;
                }
            }
        }
    }
}