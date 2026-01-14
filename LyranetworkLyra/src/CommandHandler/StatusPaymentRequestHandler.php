<?php

declare(strict_types=1);

namespace Lyranetwork\Lyra\CommandHandler;

use Lyranetwork\Lyra\Command\StatusPaymentRequest;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

use Lyranetwork\Lyra\Sdk\Tools;
use Lyranetwork\Lyra\Service\ConfigService;
use Lyranetwork\Lyra\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;

use Psr\Log\LoggerInterface;

#[AsMessageHandler]
final class StatusPaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
        private LoggerInterface $logger,
        private RequestStack $requestStack,
        private ConfigService $configService,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(StatusPaymentRequest $statusPaymentRequest): void
    {
        $session = $this->requestStack->getSession();
        $locale = $this->requestStack->getCurrentRequest()->getLocale();

        $paymentRequest = $this->paymentRequestProvider->provide($statusPaymentRequest);
        $payment = $paymentRequest->getPayment();
        $details = $payment->getDetails();

        if (! isset($details['new_status']) || $details['new_status'] == PaymentInterface::STATE_NEW) {
            $this->logger->error("Something went wrong, IPN didn't worked properly.");
            $session->getFlashBag()->add('warning', $this->translator->trans('sylius_lyra_plugin.payment.check_url_warn', locale: $locale()));

            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_FAIL,
            );

            return;
        }

        if ($payment->getState() != $details['new_status']) {
            $this->logger->error("IPN has been processed but something went wrong while update payment status.");
            $session->getFlashBag()->add('warning', $this->translator->trans('sylius_lyra_plugin.payment.check_url_warn', locale: $locale()));

            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_FAIL,
            );

            return;
        }

        $instanceCode = $paymentRequest->getMethod()->getCode();
        if ($this->configService->get(GatewayConfiguration::$REST_FIELDS . 'mode', $instanceCode) === 'TEST' && Tools::$pluginFeatures['prodfaq']) {
            $session->getFlashBag()->add('info', $this->translator->trans('sylius_lyra_plugin.payment.prodfaq', locale: $locale));
        }

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_COMPLETE,
        );
    }
}
