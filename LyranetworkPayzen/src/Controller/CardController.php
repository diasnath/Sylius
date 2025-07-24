<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

namespace Lyranetwork\Payzen\Controller;

use Lyranetwork\Payzen\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Payzen\Repository\PaymentMethodRepositoryInterface;
use Lyranetwork\Payzen\Sdk\RestData;
use Lyranetwork\Payzen\Service\ConfigService;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\RouterInterface;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;

use Twig\Environment;

class CardController
{
    /**
     * @var CustomerContextInterface
     */
    private $customerContext;

    /**
     * @var CurrencyContextInterface
     */
    private $currencyContext;

    /**
     * @var RestData
     */
    private $restData;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Environment
     */
    private  $twig;

    /**
     * @var PaymentMethodRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var RouterInterface
     */
    private $router;

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
        CustomerRepositoryInterface $customerRepository,
        CustomerContextInterface $customerContext,
        CurrencyContextInterface $currencyContext,
        RestData $restData,
        RouterInterface $router,
        Environment $twig,
        ConfigService $configService,
        ChannelContextInterface $channelContext
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->customerRepository = $customerRepository;
        $this->customerContext = $customerContext;
        $this->currencyContext = $currencyContext;
        $this->restData = $restData;
        $this->router = $router;
        $this->twig = $twig;
        $this->configService = $configService;
        $this->channelContext = $channelContext;
    }

    public function savedCardsAction(Request $request)
    {
        $gatewayName = constant('Lyranetwork\Payzen\Sdk\Tools::FACTORY_NAME');
        $payzenPaymentMethods = $this->paymentMethodRepository->findAllByGatewayName($gatewayName);
        $walletAllowed = false;

        if (is_array($payzenPaymentMethods) && ! empty($payzenPaymentMethods)) {
            foreach ($payzenPaymentMethods as $payzenPaymentMethod) {
                if ($payzenPaymentMethod->isEnabled()
                    && $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'oneclick_payment', $payzenPaymentMethod->getCode())
                    && $payzenPaymentMethod->hasChannel($this->channelContext->getChannel())
                ) {
                    $walletAllowed = true;
                    $instanceCode = $payzenPaymentMethod->getCode();

                    break;
                }
            }
        }

        $customer = $this->customerContext->getCustomer();
        if (! $walletAllowed || ! $customer) {
            return new RedirectResponse($this->router->generate('sylius_shop_account_dashboard'));
        }

        $cust = $this->customerRepository->find($customer->getId());
        $accountToken = $this->restData->getAccountToken($cust, $this->currencyContext->getCurrencyCode(), $instanceCode);

        return new Response($this->twig->render('@LyranetworkPayzenPlugin/shop/account/saved_cards.html.twig', ['accountToken' => $accountToken, 'instanceCode' => $instanceCode]));
    }
}