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

use Lyranetwork\Lyra\Repository\OrderRepositoryInterface;

class OrderService
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository
    )
    {
        $this->orderRepository = $orderRepository;
    }

    public function get(string $orderId)
    {
        return $this->orderRepository->findOneByOrderId($orderId);
    }

    public function getByNumber(string $orderNumber)
    {
        return $this->orderRepository->findOneByNumber($orderNumber);
    }
}