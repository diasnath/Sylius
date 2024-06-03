<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Lyra\Repository;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface as BasePaymentMethodRepositoryInterface;

interface PaymentMethodRepositoryInterface extends BasePaymentMethodRepositoryInterface
{
    public function findByGatewayNameAndCode(string $gatewayFactoryName, string $code);

    public function findAllByGatewayName(mixed $gatewayName);
}