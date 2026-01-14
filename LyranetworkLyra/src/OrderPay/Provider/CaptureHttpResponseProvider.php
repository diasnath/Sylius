<?php

declare(strict_types=1);

namespace Lyranetwork\Lyra\OrderPay\Provider;

use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class CaptureHttpResponseProvider implements HttpResponseProviderInterface
{
    public function __construct(
        private Environment $twig,
        private UrlProviderInterface $afterPayUrlProvider,
    ) {}

    public function supports(RequestConfiguration $requestConfiguration, PaymentRequestInterface $paymentRequest): bool
    {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_CAPTURE;
    }

    public function getResponse(RequestConfiguration $requestConfiguration, PaymentRequestInterface $paymentRequest): Response
    {
        $data = $paymentRequest->getResponseData();
        $payment = $paymentRequest->getPayment();
        $order = $payment->getOrder();
        $method = $paymentRequest->getMethod();

        // Example: Display a Twig template
        return new Response(
            $this->twig->render(
                '@LyranetworkLyraPlugin/shop/checkout/checkout.html.twig',
                [
                    'order' => $order,
                    'method' => $method,
                    'return_url' => $this->afterPayUrlProvider->getUrl($paymentRequest, UrlGeneratorInterface::ABSOLUTE_URL),
                    'paymentRequestHash' => $paymentRequest->getHash()
                ]
            )
        );
    }
}