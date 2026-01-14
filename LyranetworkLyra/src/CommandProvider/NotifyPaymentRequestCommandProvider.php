<?php

declare(strict_types=1);

namespace Lyranetwork\Lyra\CommandProvider;

use Lyranetwork\Lyra\Command\NotifyPaymentRequest;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class NotifyPaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
{
    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        return true;
    }

    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        return new NotifyPaymentRequest($paymentRequest->getId());
    }
}