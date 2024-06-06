<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

namespace Lyranetwork\Payzen\Repository;

use Lyranetwork\Payzen\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\OrderRepository as BaseOrderRepository;

class OrderRepository extends BaseOrderRepository implements OrderRepositoryInterface
{
    public function findOneByOrderId(string $orderId): ?OrderInterface
    {
        $result = $this->createQueryBuilder('o')
            ->where('o.id = :orderId')
            ->setParameter('orderId', $orderId)
            ->getQuery()
            ->setMaxResults(1)
            ->getResult();

        return $result[0] ?? null;
    }

    public function findOneByNumber(string $orderNumber): ?OrderInterface
    {
        $result = $this->createQueryBuilder('o')
            ->where('o.number = :orderNumber')
            ->setParameter('orderNumber', $orderNumber)
            ->getQuery()
            ->setMaxResults(1)
            ->getResult();

        return $result[0] ?? null;
    }
}