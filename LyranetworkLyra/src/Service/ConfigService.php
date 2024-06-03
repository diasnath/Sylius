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
namespace Lyranetwork\Lyra\Service;

use Lyranetwork\Lyra\Payum\SyliusPaymentGatewayFactory;
use Lyranetwork\Lyra\Repository\PaymentMethodRepositoryInterface;

class ConfigService
{
    /**
     * @var PaymentMethodRepositoryInterface
     */
    private $paymentMethodRepository;

    public function __construct(
        PaymentMethodRepositoryInterface $paymentMethodRepository
    )
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function get(string $configId, string $instanceCode)
    {
        $paymentMethod = $this->paymentMethodRepository->findByGatewayNameAndCode(SyliusPaymentGatewayFactory::FACTORY_NAME, $instanceCode);

        if (! $paymentMethod) {
            return "";
        }

        $config = $paymentMethod->getGatewayConfig()->getConfig();
        if (! isset($config[$configId])) {
            return "";
        }

        return $config[$configId];
    }
}