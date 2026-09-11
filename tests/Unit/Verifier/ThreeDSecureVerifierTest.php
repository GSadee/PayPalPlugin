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

namespace Tests\Sylius\PayPalPlugin\Unit\Verifier;

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifier;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifierInterface;

final class ThreeDSecureVerifierTest extends TestCase
{
    private ThreeDSecureVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new ThreeDSecureVerifier();
    }

    public function test_it_implements_three_d_secure_verifier_interface(): void
    {
        self::assertInstanceOf(ThreeDSecureVerifierInterface::class, $this->verifier);
    }

    public function test_it_accepts_an_order_carrying_no_authentication_result(): void
    {
        $this->verifier->verify(['id' => 'PAYPAL_ORDER_ID', 'status' => 'APPROVED']);

        $this->expectNotToPerformAssertions();
    }

    public function test_it_accepts_an_order_whose_payment_source_is_not_a_card(): void
    {
        $this->verifier->verify(['payment_source' => ['paypal' => ['email_address' => 'buyer@example.com']]]);

        $this->expectNotToPerformAssertions();
    }

    public function test_it_accepts_a_successful_authentication(): void
    {
        $this->verifier->verify($this->orderDetails('Y', 'Y', 'POSSIBLE'));

        $this->expectNotToPerformAssertions();
    }

    public function test_it_accepts_an_attempted_authentication(): void
    {
        $this->verifier->verify($this->orderDetails('Y', 'A', 'POSSIBLE'));

        $this->expectNotToPerformAssertions();
    }

    public function test_it_rejects_a_failed_authentication(): void
    {
        self::assertFalse($this->rejectionOf($this->orderDetails('Y', 'N', 'NO'))->isRetryable());
    }

    public function test_it_rejects_an_authentication_the_issuer_refused(): void
    {
        self::assertFalse($this->rejectionOf($this->orderDetails('Y', 'R', 'NO'))->isRetryable());
    }

    public function test_it_asks_to_retry_an_authentication_that_could_not_be_completed(): void
    {
        self::assertTrue($this->rejectionOf($this->orderDetails('Y', 'U', 'UNKNOWN'))->isRetryable());
    }

    public function test_it_asks_to_retry_a_challenge_the_buyer_did_not_finish(): void
    {
        self::assertTrue($this->rejectionOf($this->orderDetails('Y', 'C', 'UNKNOWN'))->isRetryable());
    }

    public function test_it_asks_to_retry_an_unrecognised_authentication_status(): void
    {
        self::assertTrue($this->rejectionOf($this->orderDetails('Y', 'I', 'UNKNOWN'))->isRetryable());
    }

    public function test_it_accepts_a_card_that_is_not_enrolled(): void
    {
        $this->verifier->verify($this->orderDetails('N', null, 'NO'));

        $this->expectNotToPerformAssertions();
    }

    public function test_it_accepts_an_unavailable_authentication_system(): void
    {
        $this->verifier->verify($this->orderDetails('U', null, 'NO'));

        $this->expectNotToPerformAssertions();
    }

    public function test_it_asks_to_retry_an_unavailable_authentication_system_without_liability_shift(): void
    {
        self::assertTrue($this->rejectionOf($this->orderDetails('U', null, 'UNKNOWN'))->isRetryable());
    }

    public function test_it_accepts_a_bypassed_authentication(): void
    {
        $this->verifier->verify($this->orderDetails('B', null, 'NO'));

        $this->expectNotToPerformAssertions();
    }

    public function test_it_asks_to_retry_an_authentication_result_carrying_no_enrollment_status(): void
    {
        self::assertTrue($this->rejectionOf(['payment_source' => ['card' => ['authentication_result' => []]]])->isRetryable());
    }

    private function rejectionOf(array $paypalOrderDetails): ThreeDSecureAuthenticationFailedException
    {
        try {
            $this->verifier->verify($paypalOrderDetails);
        } catch (ThreeDSecureAuthenticationFailedException $exception) {
            return $exception;
        }

        self::fail('Expected the verifier to reject the authentication result.');
    }

    private function orderDetails(string $enrollmentStatus, ?string $authenticationStatus, string $liabilityShift): array
    {
        $threeDSecure = ['enrollment_status' => $enrollmentStatus];
        if (null !== $authenticationStatus) {
            $threeDSecure['authentication_status'] = $authenticationStatus;
        }

        return [
            'payment_source' => [
                'card' => [
                    'authentication_result' => [
                        'liability_shift' => $liabilityShift,
                        'three_d_secure' => $threeDSecure,
                    ],
                ],
            ],
        ];
    }
}
