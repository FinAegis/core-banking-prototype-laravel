<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Payment\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Services\DemoPaymentGatewayService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DemoPaymentGatewayServiceTest extends TestCase
{
    use RefreshDatabase;

    private DemoPaymentGatewayService $service;

    private PaymentServiceInterface&MockInterface $paymentService;

    protected User $user;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and account
        $this->user = User::factory()->create([
            'stripe_id' => 'cus_demo_3', // Set demo stripe ID for testing
        ]);
        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'uuid'      => 'test-account-uuid',
        ]);

        // Ensure the relationship is loaded
        $this->user->load('accounts');

        // Mock the payment service
        /** @var PaymentServiceInterface&MockInterface $paymentService */
        $paymentService = Mockery::mock(PaymentServiceInterface::class);
        $this->paymentService = $paymentService;

        // Create the demo gateway service with mocked payment service
        $this->service = new DemoPaymentGatewayService($this->paymentService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_deposit_intent_returns_mock_payment_intent(): void
    {
        $amountInCents = 10000; // $100
        $currency = 'USD';

        $intent = $this->service->createDepositIntent($this->user, $amountInCents, $currency);

        // Assert the mock payment intent has expected properties
        $this->assertStringStartsWith('demo_pi_', $intent->id);
        $this->assertEquals('payment_intent', $intent->object);
        $this->assertEquals($amountInCents, $intent->amount);
        $this->assertEquals('usd', $intent->currency);
        $this->assertEquals('requires_payment_method', $intent->status);
        $this->assertStringStartsWith('demo_secret_', $intent->client_secret);
        $this->assertArrayHasKey('demo_mode', $intent->metadata);
        $this->assertTrue($intent->metadata['demo_mode']);
        $this->assertEquals($this->user->id, $intent->metadata['user_id']);
        $this->assertEquals($this->account->uuid, $intent->metadata['account_uuid']);
    }

    public function test_process_deposit_calls_payment_service(): void
    {
        // First create a payment intent to ensure data is stored
        $intent = $this->service->createDepositIntent($this->user, 10000, 'USD');
        $paymentIntentId = $intent->id;

        // Set up the expectation for the payment service
        $this->paymentService
            ->shouldReceive('processStripeDeposit')
            ->once()
            ->with(Mockery::on(function ($data) use ($paymentIntentId) {
                return $data['account_uuid'] === $this->account->uuid
                    && $data['amount'] === 10000
                    && $data['currency'] === 'USD'
                    && $data['external_reference'] === $paymentIntentId
                    && $data['payment_method'] === 'demo_card'
                    && $data['metadata']['demo_mode'] === true;
            }))
            ->andReturn('demo_pi_processed');

        $result = $this->service->processDeposit($paymentIntentId);

        // Assert the result
        $this->assertArrayHasKey('reference', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertStringStartsWith('DEP-', $result['reference']);
        $this->assertEquals('succeeded', $result['status']);
    }

    public function test_get_saved_payment_methods_returns_demo_methods(): void
    {
        $methods = $this->service->getSavedPaymentMethods($this->user);

        $this->assertCount(2, $methods);

        // Check card method
        $cardMethod = $methods[0];
        $this->assertEquals('demo_pm_card', $cardMethod->id);
        $this->assertEquals('card', $cardMethod->type);
        $this->assertEquals('visa', $cardMethod->card->brand);
        $this->assertEquals('4242', $cardMethod->card->last4);

        // Check bank method
        $bankMethod = $methods[1];
        $this->assertEquals('demo_pm_bank', $bankMethod->id);
        $this->assertEquals('bank_account', $bankMethod->type);
        $this->assertEquals('Demo Bank', $bankMethod->bank_account->bank_name);
        $this->assertEquals('1234', $bankMethod->bank_account->last4);
        $this->assertEquals($this->user->name, $bankMethod->bank_account->account_holder_name);
    }

    public function test_add_payment_method_returns_mock_cashier_method(): void
    {
        $paymentMethodId = 'demo_pm_test9876';

        $method = $this->service->addPaymentMethod($this->user, $paymentMethodId);

        /** @var object $stripeMethod */
        $stripeMethod = $method->asStripePaymentMethod();
        $this->assertEquals($paymentMethodId, $stripeMethod->id);
        $this->assertEquals('card', $stripeMethod->type);
        $this->assertEquals('visa', $stripeMethod->card->brand);
        $this->assertEquals('9876', $stripeMethod->card->last4);
    }

    public function test_remove_payment_method_succeeds_without_api_call(): void
    {
        $paymentMethodId = 'demo_pm_to_remove';

        // This should not throw an exception and complete quickly
        $startTime = microtime(true);

        $this->service->removePaymentMethod($this->user, $paymentMethodId);

        $executionTime = microtime(true) - $startTime;

        // Should complete instantly since it's a no-op in demo mode
        $this->assertLessThan(0.1, $executionTime);
    }

    public function test_demo_service_does_not_make_external_api_calls(): void
    {
        // Test that all operations complete quickly without network delays
        $startTime = microtime(true);

        // Create intent
        $this->service->createDepositIntent($this->user, 5000, 'USD');

        // Get payment methods
        $this->service->getSavedPaymentMethods($this->user);

        // Add payment method
        $this->service->addPaymentMethod($this->user, 'demo_pm_test');

        // Remove payment method
        $this->service->removePaymentMethod($this->user, 'demo_pm_test');

        $totalTime = microtime(true) - $startTime;

        // All operations should complete in under 0.5 seconds total
        // Real Stripe API calls would take several seconds
        $this->assertLessThan(0.5, $totalTime);
    }
}
