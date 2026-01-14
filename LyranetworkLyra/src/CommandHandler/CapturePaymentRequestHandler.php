<?php

declare(strict_types=1);

namespace Lyranetwork\Lyra\CommandHandler;

use Lyranetwork\Lyra\Command\CapturePaymentRequest;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

#[AsMessageHandler]
final class CapturePaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
    ) {}

    public function __invoke(CapturePaymentRequest $capturePaymentRequest): void
    {
        // Retrieve the current PaymentRequest based on the hash provided in the CapturePaymentRequest command
        $paymentRequest = $this->paymentRequestProvider->provide($capturePaymentRequest);

        if (PaymentRequestInterface::STATE_PROCESSING === $paymentRequest->getState()) {
            return;
        }

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_COMPLETE
        );
    }
}