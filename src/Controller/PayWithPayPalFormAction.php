<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\IdentityApiInterface;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalWebSdkConfigurationProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class PayWithPayPalFormAction
{
    /** @param PaymentRepositoryInterface<PaymentInterface> $paymentRepository */
    public function __construct(
        private Environment $twig,
        private PaymentRepositoryInterface $paymentRepository,
        private ?AvailableCountriesProviderInterface $countriesProvider = null,
        private ?CacheAuthorizeClientApiInterface $authorizeClientApi = null,
        private ?IdentityApiInterface $identityApi = null,
        private ?LocaleProcessorInterface $localeProcessor = null,
        private ?PayPalConfigurationProviderInterface $payPalConfigurationProvider = null,
        private ?PayPalWebSdkConfigurationProviderInterface $webSdkConfigurationProvider = null,
        private ?UrlGeneratorInterface $router = null,
    ) {
        $this->deprecateUnusedArgument($this->countriesProvider, AvailableCountriesProviderInterface::class);
        $this->deprecateUnusedArgument($this->authorizeClientApi, CacheAuthorizeClientApiInterface::class);
        $this->deprecateUnusedArgument($this->identityApi, IdentityApiInterface::class);
        $this->deprecateUnusedArgument($this->payPalConfigurationProvider, PayPalConfigurationProviderInterface::class);

        if (null === $this->localeProcessor) {
            trigger_deprecation(
                'SyliusPayPalPlugin',
                '1.7',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 3.0.',
                LocaleProcessorInterface::class,
                self::class,
            );
        }
        if (null === $this->webSdkConfigurationProvider || null === $this->router) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing an instance of %s and a router to %s constructor is deprecated and will be required in 3.0.',
                PayPalWebSdkConfigurationProviderInterface::class,
                self::class,
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        $orderToken = (string) $request->attributes->get('orderToken');
        $payment = $this->paymentRepository->findOneByOrderToken($request->attributes->get('paymentId'), $orderToken);

        if (null === $payment) {
            throw new NotFoundHttpException(sprintf('There is no PayPal payment for order "%s".', $orderToken));
        }

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
            return new RedirectResponse($this->getRouter()->generate('sylius_shop_order_thank_you'));
        }

        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();
        $locale = null !== $this->localeProcessor
            ? $this->localeProcessor->process($request->getLocale())
            : $request->getLocale();

        $response = new Response($this->twig->render('@SyliusPayPalPlugin/pay_with_paypal.html.twig', [
            'billingAddress' => $order->getBillingAddress(),
            'cancelPayPalPaymentUrl' => $this->getRouter()->generate('sylius_paypal_shop_cancel_checkout_payment'),
            'completePayPalOrderUrl' => $this->getRouter()->generate(
                'sylius_paypal_shop_complete_paypal_order',
                ['token' => $order->getTokenValue()],
            ),
            'createPayPalOrderUrl' => $this->getRouter()->generate(
                'sylius_paypal_shop_create_paypal_order',
                ['token' => $order->getTokenValue()],
            ),
            'currency' => $order->getCurrencyCode(),
            'errorPayPalPaymentUrl' => $this->getRouter()->generate('sylius_paypal_shop_payment_error'),
            'order' => $order,
            'payment' => $payment,
            'webSdkInstanceConfig' => $this->getWebSdkConfigurationProvider()->getInstanceConfig(
                $channel,
                'checkout',
                [...PayPalWebSdkConfigurationProviderInterface::DEFAULT_COMPONENTS, 'card-fields'],
                $locale,
            ),
            'webSdkScriptUrl' => $this->getWebSdkConfigurationProvider()->getScriptUrl(),
        ]));

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function deprecateUnusedArgument(?object $argument, string $interface): void
    {
        if (null === $argument) {
            return;
        }

        trigger_deprecation(
            'sylius/paypal-plugin',
            '2.1',
            'Passing an instance of "%s" to "%s" constructor is deprecated and will be prohibited in 3.0.' .
            ' It is no longer used since the page moved to PayPal Web SDK v6.',
            $interface,
            self::class,
        );
    }

    private function getWebSdkConfigurationProvider(): PayPalWebSdkConfigurationProviderInterface
    {
        if (null === $this->webSdkConfigurationProvider) {
            throw new \RuntimeException(sprintf(
                'An instance of "%s" is required to render the v6 Web SDK payment page.',
                PayPalWebSdkConfigurationProviderInterface::class,
            ));
        }

        return $this->webSdkConfigurationProvider;
    }

    private function getRouter(): UrlGeneratorInterface
    {
        if (null === $this->router) {
            throw new \RuntimeException('A router is required to render the v6 Web SDK payment page.');
        }

        return $this->router;
    }
}
