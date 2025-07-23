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

namespace Lyranetwork\Lyra\EventListener;

use Lyranetwork\Lyra\Repository\PaymentMethodRepositoryInterface;
use Lyranetwork\Lyra\Service\ConfigService;
use Lyranetwork\Lyra\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;

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
        $gatewayName = constant('Lyranetwork\\Lyra\\Sdk\\Tools::FACTORY_NAME');
        $lyraPaymentMethods = $this->paymentMethodRepository->findAllByGatewayName($gatewayName);

        if (is_array($lyraPaymentMethods) && ! empty($lyraPaymentMethods)) {
            foreach ($lyraPaymentMethods as $lyraPaymentMethod) {
                if ($lyraPaymentMethod->isEnabled()
                    && $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'oneclick_payment', $lyraPaymentMethod->getCode())
                    && $lyraPaymentMethod->hasChannel($this->channelContext->getChannel())
                ) {
                    $menu = $event->getMenu();

                    $menu->addChild('card', ['route' => 'lyra_sylius_account_saved_cards'])
                        ->setAttribute('type', 'link')
                        ->setLabel('sylius_lyra_plugin.ui.account.saved_cards.title')
                        ->setLabelAttribute('icon', 'tabler:credit-card');

                    break;
                }
            }
        }
    }
}