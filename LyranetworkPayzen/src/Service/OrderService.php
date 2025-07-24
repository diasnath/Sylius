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

namespace Lyranetwork\Payzen\Service;

use Lyranetwork\Payzen\Repository\OrderRepositoryInterface;

use Sylius\Bundle\CoreBundle\Mailer\OrderEmailManagerInterface;
use App\Entity\Payment\Payment;
use Symfony\Component\HttpClient\Exception\TransportException;

use Webmozart\Assert\Assert;

use Psr\Log\LoggerInterface;

class OrderService
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderEmailManagerInterface
     */
    private $orderEmailManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderEmailManagerInterface $orderEmailManager,
        LoggerInterface $logger
    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderEmailManager = $orderEmailManager;
        $this->logger = $logger;
    }

    public function get(string $orderId)
    {
        return $this->orderRepository->findOneByOrderId($orderId);
    }

    public function getByNumber(string $orderNumber)
    {
        return $this->orderRepository->findOneByNumber($orderNumber);
    }

    public function sendConfirmationEmail(Payment $payment): void
    {
        try {
            Assert::isInstanceOf($payment, Payment::class);

            $gatewayName = constant('Lyranetwork\Payzen\Sdk\Tools::FACTORY_NAME');
            $factoryName = $payment->getMethod()->getGatewayConfig()->getFactoryName();
            if ($gatewayName === $factoryName) {
                $order = $payment->getOrder();

                $this->logger->info("Sending confirmation email for order: " . $order->getNumber());

                $this->orderEmailManager->sendConfirmationEmail($order);
            }
        } catch (TransportException $e) {
            $this->logger->error($e->getMessage());
        }
    }

    public function getByTokenValue($tokenValue)
    {
        return $this->orderRepository->findOneByTokenValue($tokenValue);
    }
}