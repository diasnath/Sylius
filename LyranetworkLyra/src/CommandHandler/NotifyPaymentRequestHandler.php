<?php

declare(strict_types=1);

namespace Lyranetwork\Lyra\CommandHandler;

use Lyranetwork\Lyra\Command\NotifyPaymentRequest;
use Lyranetwork\Lyra\Sdk\RestData;
use Lyranetwork\Lyra\Sdk\Form\Response as LyraResponse;
use Lyranetwork\Lyra\Sdk\Tools;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\HttpFoundation\Response;

use Psr\Log\LoggerInterface;

#[AsMessageHandler]
final class NotifyPaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
        private RestData $restData,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(NotifyPaymentRequest $capturePaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($capturePaymentRequest);
        $payment = $paymentRequest->getPayment();

        $details = $payment->getDetails();
        $answer = json_decode($details['answer'], true);

        $params = $this->restData->convertRestResult($answer);
        $lyraResponse = new LyraResponse(
            $params,
            "",
            "",
            ""
        );

        $orderId = $lyraResponse->get('order_id');

        // Ignore IPN on cancelation for already registered orders.
        if (($lyraResponse->getTransStatus() === 'ABANDONED') ||
            (($lyraResponse->getTransStatus() === 'CANCELLED')
                && ((($lyraResponse->get('order_status') === 'UNPAID') && ($lyraResponse->get('order_cycle') === 'CLOSED')) || ($lyraResponse->get('url_check_src') !== 'MERCH_BO')))) {
            $this->logger->info('Server call on cancellation for order #' . $orderId . '. No order will be updated.');

            $details['responseCode'] = Response::HTTP_OK;
            $details['responseMessage'] = '<span style="display:none">KO-Payment abandoned. \n</span>';
            $payment->setDetails($details);

            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_COMPLETE,
            );

            return;
        }

        $this->logger->info("Server call process starts for order #$orderId.");
        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_PROCESS,
        );

        $amount = $payment->getAmount();
        $details['lyra_factory_name'] = Tools::FACTORY_NAME;
        $details['lyra_trans_id'] = $lyraResponse->get('trans_id');
        $details['lyra_trans_uuid'] = $lyraResponse->get('trans_uuid');
        $details['lyra_card_brand'] = $lyraResponse->get('vads_card_brand');
        $details['lyra_payment_initial_amount'] = $amount;
        $details['new_status'] = PaymentInterface::STATE_NEW;

        $msg = '';
        if ($lyraResponse->isPendingPayment()) {
            $this->logger->info("Payment pending for order #$orderId. New payment status: " . PaymentInterface::STATE_PROCESSING);
            $details['new_status'] = PaymentInterface::STATE_PROCESSING;
            if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS)) {
                $msg = 'payment_ok';
                $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS);

                $this->logger->info("Pending payment processed successfully for order #$orderId.");
            } else {
                $this->logger->info("Payment pending processing failed for order #$orderId.");
            }
        } elseif ($lyraResponse->isAcceptedPayment()) {
            $this->logger->info("Payment accepted for order #$orderId. New payment status: " . PaymentInterface::STATE_COMPLETED);
            $msg = 'payment_ok';
            $details['new_status'] = PaymentInterface::STATE_COMPLETED;

            if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)) {
                $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);

                $this->logger->info("Payment status processed successfully for order #$orderId.");
            } else {
                $this->logger->info("Payment accepted, payment status processing failed for order #$orderId.");
            }
        } elseif ($lyraResponse->get('order_cycle') === 'CLOSED' || ($lyraResponse->get('url_check_src') === 'MERCH_BO')) {
            $msg = 'payment_ko';
            if ($lyraResponse->isCancelledPayment()) {
                $this->logger->info("Payment cancelled for order #$orderId. {$lyraResponse->getLogMessage()}");
                $details['new_status'] = PaymentInterface::STATE_CANCELLED;
                if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)) {
                    $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);

                    $this->logger->info("Payment status processed successfully for order #$orderId.");
                } else {
                    $this->logger->info("Payment cancelled, payment status processing failed for order #$orderId.");
                }
            } else {
                $this->logger->info("Payment failed for order #$orderId. {$lyraResponse->getLogMessage()}");
                $details['new_status'] = PaymentInterface::STATE_FAILED;
                if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL)) {
                    $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL);

                    $this->logger->info("Payment status processed successfully for order #$orderId.");
                } else {
                    $this->logger->info("Payment failed, payment status processing failed for order #$orderId.");
                }
            }
        }

        $details['responseCode'] = Response::HTTP_OK;
        $details['responseMessage'] = $lyraResponse->getOutputForGateway($msg);

        $payment->setDetails($details);

        $this->logger->info("IPN URL process end for order #$orderId.");

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_COMPLETE,
        );
    }
}
