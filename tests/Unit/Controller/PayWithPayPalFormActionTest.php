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

namespace Tests\Sylius\PayPalPlugin\Unit\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Controller\PayWithPayPalFormAction;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\PayPalWebSdkConfigurationProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PayWithPayPalFormActionTest extends TestCase
{
    private Environment&MockObject $twig;

    private PaymentRepositoryInterface&MockObject $paymentRepository;

    private PayPalWebSdkConfigurationProviderInterface&MockObject $webSdkConfigurationProvider;

    private PayWithPayPalFormAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->twig = $this->createMock(Environment::class);
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->webSdkConfigurationProvider = $this->createMock(PayPalWebSdkConfigurationProviderInterface::class);
        $this->webSdkConfigurationProvider->method('getScriptUrl')->willReturn('SCRIPT_URL');

        $localeProcessor = $this->createMock(LocaleProcessorInterface::class);
        $localeProcessor->method('process')->willReturnArgument(0);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route): string => $route);

        $this->action = new PayWithPayPalFormAction(
            twig: $this->twig,
            paymentRepository: $this->paymentRepository,
            localeProcessor: $localeProcessor,
            webSdkConfigurationProvider: $this->webSdkConfigurationProvider,
            router: $router,
        );
    }

    public function test_it_renders_the_page_for_a_payment_awaiting_payment(): void
    {
        $this->payment(PaymentInterface::STATE_NEW);

        $this->twig->expects(self::once())->method('render')->willReturn('RENDERED');

        $response = ($this->action)($this->request());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('RENDERED', $response->getContent());
    }

    public function test_it_asks_the_sdk_instance_for_the_card_fields_component(): void
    {
        $this->payment(PaymentInterface::STATE_NEW);

        $this->webSdkConfigurationProvider
            ->expects(self::once())
            ->method('getInstanceConfig')
            ->with(self::anything(), 'checkout', ['paypal-payments', 'card-fields'], 'en_US')
            ->willReturn(['clientId' => 'CLIENT_ID'])
        ;

        ($this->action)($this->request());
    }

    public function test_it_hands_the_template_the_urls_the_page_calls(): void
    {
        $this->payment(PaymentInterface::STATE_NEW);

        $this->twig->expects(self::once())->method('render')->willReturnCallback(
            static function (string $name, array $context): string {
                self::assertSame('sylius_paypal_shop_create_paypal_order', $context['createPayPalOrderUrl']);
                self::assertSame('sylius_paypal_shop_complete_paypal_order', $context['completePayPalOrderUrl']);
                self::assertSame('sylius_paypal_shop_cancel_checkout_payment', $context['cancelPayPalPaymentUrl']);
                self::assertSame('sylius_paypal_shop_payment_error', $context['errorPayPalPaymentUrl']);
                self::assertSame('SCRIPT_URL', $context['webSdkScriptUrl']);

                return 'RENDERED';
            },
        );

        ($this->action)($this->request());
    }

    public function test_it_sends_the_buyer_to_the_thank_you_page_when_the_payment_is_already_completed(): void
    {
        $this->payment(PaymentInterface::STATE_COMPLETED);

        $this->twig->expects(self::never())->method('render');

        $response = ($this->action)($this->request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('sylius_shop_order_thank_you', $response->getTargetUrl());
    }

    public function test_it_answers_with_not_found_when_the_payment_does_not_exist(): void
    {
        $this->paymentRepository->method('findOneByOrderToken')->willReturn(null);

        $this->twig->expects(self::never())->method('render');
        $this->expectException(NotFoundHttpException::class);

        ($this->action)($this->request());
    }

    public function test_it_forbids_storing_the_rendered_page(): void
    {
        $this->payment(PaymentInterface::STATE_NEW);
        $this->twig->method('render')->willReturn('RENDERED');

        $response = ($this->action)($this->request());

        self::assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
    }

    private function payment(string $state): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getChannel')->willReturn($this->createMock(ChannelInterface::class));
        $order->method('getTokenValue')->willReturn('ORDER_TOKEN');
        $order->method('getCurrencyCode')->willReturn('USD');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn($state);
        $payment->method('getOrder')->willReturn($order);

        $this->paymentRepository->method('findOneByOrderToken')->willReturn($payment);
    }

    private function request(): Request
    {
        $request = new Request(attributes: ['orderToken' => 'ORDER_TOKEN', 'paymentId' => '1']);
        $request->setLocale('en_US');

        return $request;
    }
}
