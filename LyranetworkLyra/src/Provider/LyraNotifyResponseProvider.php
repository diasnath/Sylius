<?php

declare(strict_types=1);

namespace Lyranetwork\Lyra\Provider;

use Sylius\Bundle\PaymentBundle\Provider\NotifyResponseProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Response;


final class LyraNotifyResponseProvider implements NotifyResponseProviderInterface
{
    public function __construct(private NotifyResponseProviderInterface $inner) {}

    public function provide(PaymentRequestInterface $paymentRequest): Response
    {
        $details = $paymentRequest->getPayment()->getDetails();
        if (isset($details['responseCode'], $details['responseMessage'])){
            return new Response($details['responseMessage'], $details['responseCode']);
        }

        return $this->inner->provide($paymentRequest);
    }
}