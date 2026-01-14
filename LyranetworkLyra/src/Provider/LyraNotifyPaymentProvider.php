<?php

declare(strict_types=1);

namespace Lyranetwork\Lyra\Provider;

use Sylius\Bundle\PaymentBundle\Provider\NotifyPaymentProviderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;

use Lyranetwork\Lyra\Sdk\RestData;

use Psr\Log\LoggerInterface;

final class LyraNotifyPaymentProvider implements NotifyPaymentProviderInterface
{
    public function __construct(
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private LoggerInterface $logger,
        private RestData $restData,
    ) {
    }

    public function getPayment(Request $request, PaymentMethodInterface $paymentMethod): PaymentInterface
    {
        if (! $this->restData->checkRestResponseValidity($request)) {
            $this->logger->error('Invalid response received. Content: ' . json_encode($request->request->all()));
            die('<span style="display:none">KO-Invalid IPN request received.' . "\n" . '</span>');
        }

        $answer = json_decode((string) $request->get('kr-answer'), true);
        if (! is_array($answer) || empty($answer)) {
            $this->logger->error('Invalid response received. Content of kr-answer: ' . json_encode($request->get('kr-answer')));
            die('<span style="display:none">KO-Invalid IPN request received.' . "\n" . '</span>');
        }

        $transaction = $answer['transactions'][0];

        // Ignore IPN call for adding card in the customer wallet.
        if ($transaction['operationType'] === 'VERIFICATION') {
            die();
        }

        $instanceCode = $transaction['metadata']['db_method_code'];
        $key = $this->restData->getPrivateKey($instanceCode);
        if (! $this->restData->checkResponseHash($request, $key)) {
            $this->logger->error("Tried to access lyra/rest/ipn page without valid signature.");
            $this->logger->error('Signature algorithm selected in module settings must be the same as one selected in Lyra Expert Back Office.');

            die('<span style="display:none">An error occurred while computing the signature..' . "\n" . '</span>');
        }

        $answer["kr-src"] = $request->get('kr-src');

        $hash = $transaction['metadata']['paymentRequestHash'];
        $paymentRequest = $this->paymentRequestRepository->findOneBy([
            'hash' => $hash,
        ]);

        if (null === $paymentRequest) {
            die('<span style="display:none">KO-No Payment Request found.' . "\n" . '</span>');
        }

        $payment = $paymentRequest->getPayment();
        $payment->setDetails(['answer' => json_encode($answer)]);

        return $payment;
    }

    public function supports(Request $request, PaymentMethodInterface $paymentMethod): bool
    {
        return $paymentMethod->getGatewayConfig()?->getFactoryName() === 'lyra_sylius_payment';
    }
}
