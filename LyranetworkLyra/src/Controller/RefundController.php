<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace Lyranetwork\Lyra\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lyranetwork\Lyra\Sdk\Tools as LyraTools;
use Lyranetwork\Lyra\Service\ConfigService;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Core\Model\PaymentInterface;
use SM\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Lyranetwork\Lyra\Sdk\RefundProcessor as LyraRefundProcessor;
use Lyranetwork\Lyra\Sdk\RestData;
use Lyranetwork\Lyra\Sdk\Refund\OrderInfo as LyraOrderInfo;
use Lyranetwork\Lyra\Sdk\Refund\Api as LyraRefund;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;
use Lyranetwork\Lyra\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;

use Psr\Log\LoggerInterface;

use Lyranetwork\Lyra\Service\OrderService;

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
     * @var RestData
     */
    private $restData;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var LyraRefundProcessor
     */
    private $refundProcessor;

    /**
     * @var FactoryInterface
     */
    private $stateMachineFactory;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        LoggerInterface $logger,
        OrderService $orderService,
        ConfigService $configService,
        EntityManagerInterface $paymentEntityManager,
        RequestStack $requestStack,
        RestData $restData,
        LyraRefundProcessor $refundProcessor,
        FactoryInterface $stateMachineFactory
    )
    {
        $this->paymentRepository = $paymentRepository;
        $this->logger = $logger;
        $this->orderService = $orderService;
        $this->configService = $configService;
        $this->paymentEntityManager = $paymentEntityManager;
        $this->requestStack = $requestStack;
        $this->restData = $restData;
        $this->refundProcessor = $refundProcessor;
        $this->stateMachineFactory = $stateMachineFactory;
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

        if ($factoryName !== constant('Lyranetwork\Lyra\Payum\SyliusPaymentGatewayFactory::FACTORY_NAME')) {
            $this->applyStateMachineTransition($payment);

            $this->requestStack->getSession()->getFlashBag()->add('success', 'sylius.payment.refunded');
            $this->logger->info('Refunded successfully');

            return $this->redirectToReferer($request);
        }

        $orderId = $request->get('orderId');
        $order = $this->orderService->get($orderId);

        $lyraOrderInfo = new LyraOrderInfo();
        $lyraOrderInfo->setOrderRemoteId($order->getNumber());
        $lyraOrderInfo->setOrderId($order->getNumber());
        $lyraOrderInfo->setOrderReference($order->getNumber());
        $lyraOrderInfo->setOrderCurrencyIsoCode($order->getCurrencyCode());
        $lyraOrderInfo->setOrderCurrencySign($order->getCurrencyCode());
        $lyraOrderInfo->setOrderUserInfo($this->getUserInfo());

        $paymentMethodCode = $paymentMethod->getCode();

        $refundApi = new LyraRefund(
            $this->refundProcessor->getProcessor(),
            $this->restData->getPrivateKey($paymentMethodCode),
            LyraTools::getDefault('REST_URL'),
            $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'site_id', $paymentMethodCode),
            'Sylius'
        );

        $amount = $order->getTotal();
        $currency = LyraApi::findCurrencyByAlphaCode($order->getCurrencyCode());
        $amount = $currency->convertAmountToFloat($amount);

        $refundApi->refund($lyraOrderInfo, $amount);

        return $this->redirectToReferer($request);
    }

    private function applyStateMachineTransition(PaymentInterface $payment): void
    {
        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

        if (! $stateMachine->can(PaymentTransitions::TRANSITION_REFUND)) {
            throw new BadRequestHttpException();
        }

        $stateMachine->apply(PaymentTransitions::TRANSITION_REFUND);
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