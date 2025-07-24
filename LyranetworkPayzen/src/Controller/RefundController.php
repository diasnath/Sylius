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
namespace Lyranetwork\Payzen\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lyranetwork\Payzen\Service\RefundService;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Lyranetwork\Payzen\Sdk\Refund\OrderInfo as PayzenOrderInfo;
use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;

use Psr\Log\LoggerInterface;

use Lyranetwork\Payzen\Service\OrderService;

final class RefundController
{
    /**
     * @var PaymentRepositoryInterface
     */
    private $paymentRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var EntityManagerInterface
     */
    private $paymentEntityManager;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var StateMachineInterface
     */
    private $stateMachine;

    /**
     * @var RefundService
     */
    private $refundService;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        LoggerInterface $logger,
        OrderService $orderService,
        RefundService $refundService,
        EntityManagerInterface $paymentEntityManager,
        RequestStack $requestStack,
        StateMachineInterface $stateMachine
    )
    {
        $this->paymentRepository = $paymentRepository;
        $this->logger = $logger;
        $this->orderService = $orderService;
        $this->refundService = $refundService;
        $this->paymentEntityManager = $paymentEntityManager;
        $this->requestStack = $requestStack;
        $this->stateMachine = $stateMachine;
    }

    public function paymentRefundOrCancelAction(Request $request)
    {
        $payment = $this->paymentRepository->find($request->get('id'));

        if (null === $payment) {
            $this->logger->error("Payment #{$request->get('id')} not found in database.");

            throw new NotFoundHttpException();
        }

        $paymentMethod = $payment->getMethod();

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $factoryName = $gatewayConfig->getFactoryName() ?? null;

        if ($factoryName !== constant('Lyranetwork\Payzen\Sdk\Tools::FACTORY_NAME')) {
            $this->applyStateMachineTransition($payment);

            $this->requestStack->getSession()->getFlashBag()->add('success', 'sylius.payment.refunded');
            $this->logger->info('Refunded successfully');

            return $this->redirectToReferer($request);
        }

        $orderId = $request->get('orderId');
        $order = $this->orderService->get($orderId);

        $paymentMethodCode = $paymentMethod->getCode();

        $currency = PayzenApi::findCurrencyByAlphaCode($payment->getCurrencyCode());
        $amount = $currency->convertAmountToFloat($payment->getAmount());

        $this->refundService->refund($paymentMethodCode, $order, $this->getUserInfo(), $amount);

        return $this->redirectToReferer($request);
    }

    private function applyStateMachineTransition(PaymentInterface $payment): void
    {
        if (! $this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)) {
            throw new BadRequestHttpException();
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
        $this->paymentEntityManager->flush();
    }

    private function redirectToReferer(Request $request): Response
    {
        $url = $request->headers->get('referer');

        return new RedirectResponse($url);
    }

    private function getUserInfo()
    {
        $user = $this->requestStack->getCurrentRequest()->server->get('USERNAME') ?? '';
        $remoteAddr = $this->requestStack->getCurrentRequest()->server->get('REMOTE_ADDR') ?? '';
        $commentText = 'Sylius user: ' . $user;
        $commentText .= ' ; IP address: ' . $remoteAddr;

        return $commentText;
    }
}