<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

namespace Lyranetwork\Payzen\Sdk;

use Doctrine\ORM\EntityManagerInterface;

use Lyranetwork\Payzen\Sdk\Refund\Processor;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Core\Model\PaymentInterface;

use SM\Factory\FactoryInterface;

use Psr\Log\LoggerInterface;

class RefundProcessor implements Processor
{
    private LoggerInterface $logger;

    private TranslatorInterface $translator;

    private RequestStack $requestStack;

    private PaymentRepositoryInterface $paymentRepository;

    private EntityManagerInterface $paymentEntityManager;

    private FactoryInterface $stateMachineFactory;

    public function __construct(
        LoggerInterface $logger,
        TranslatorInterface $translator,
        RequestStack $requestStack,
        PaymentRepositoryInterface $paymentRepository,
        EntityManagerInterface $paymentEntityManager,
        FactoryInterface $stateMachineFactory
    )
    {
        $this->logger = $logger;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->paymentRepository = $paymentRepository;
        $this->paymentEntityManager = $paymentEntityManager;
        $this->stateMachineFactory = $stateMachineFactory;
    }

    /**
     * Action to do in case of error during refund process.
     *
     */
    public function doOnError($errorCode, $message): void
    {
        if ($errorCode === 'privateKey') {
            $this->requestStack->getSession()->getFlashBag()->add('error', $this->translate("sylius_payzen_plugin.refund.error.private_key"));
        } else {
            $this->requestStack->getSession()->getFlashBag()->add('error', $message);
        }
    }

    /**
     * Action to do after sucessful refund process.
     *
     */
    public function doOnSuccess($operationResponse, $operationType)
    {
        if (isset($operationResponse['metadata']['db_payment_id'])) {
            $db_payment_id = $operationResponse['metadata']['db_payment_id'];
            $payment = $this->paymentRepository->find($db_payment_id);
            $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

            if ($stateMachine->can(PaymentTransitions::TRANSITION_REFUND)) {
                $stateMachine->apply(PaymentTransitions::TRANSITION_REFUND);
            }

            $this->paymentEntityManager->flush();
            $this->requestStack->getSession()->getFlashBag()->add('success', 'sylius.payment.refunded');

            $this->logger->info("Refunded order #{$operationResponse['orderDetails']['orderId']} has been saved.");
        }
    }

    /**
     * Action to do after failed refund process.
     *
     */
    public function doOnFailure($errorCode, $message): void
    {
        $this->doOnError($errorCode, $message);
    }

    /**
     * Log informations.
     *
     */
    public function log($message, $level): void
    {
        $this->logger->log($level, $message);
    }

    /**
     * Translate the given message.
     *
     */
    public function translate($message)
    {
        return $this->translator->trans($message, locale: $this->requestStack->getCurrentRequest()->get('admin_locale'));
    }

    public function getProcessor()
    {
        return $this;
    }
}