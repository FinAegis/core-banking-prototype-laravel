<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Payment\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Payment\Services\SandboxPaymentService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SandboxPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private SandboxPaymentService $service;

    protected User $user;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SandboxPaymentService();

        // Create test user and account
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'uuid'      => 'test-account-uuid',
        ]);
    }

    public function test_process_stripe_deposit_adds_sandbox_metadata(): void
    {
        Log::spy();

        $data = [
            'account_uuid'        => $this->account->uuid,
            'amount'              => 10000, // $100
            'currency'            => 'USD',
            'reference'           => 'TEST-REF-' . uniqid(),
            'external_reference'  => 'stripe_pi_test123',
            'payment_method'      => 'card',
            'payment_method_type' => 'card',
            'metadata'            => ['original' => true],
        ];

        $result = $this->service->processStripeDeposit($data);

        // Assert that a sandbox transaction ID was returned
        $this->assertStringStartsWith('sandbox_txn_', $result);

        // Verify log was called with sandbox metadata
        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Processing sandbox Stripe deposit', Mockery::on(function ($arg) use ($data) {
                return $arg['environment'] === 'sandbox'
                    && $arg['account_uuid'] === $data['account_uuid']
                    && $arg['amount'] === $data['amount'];
            }));
    }

    public function test_process_bank_withdrawal_adds_sandbox_metadata(): void
    {
        Log::spy();

        $data = [
            'account_uuid'        => $this->account->uuid,
            'amount'              => 5000, // $50
            'currency'            => 'USD',
            'reference'           => 'TEST-WD-' . uniqid(),
            'bank_name'           => 'Test Bank',
            'account_number'      => '1234567890',
            'account_holder_name' => 'Test User',
            'routing_number'      => '987654321',
            'metadata'            => ['original' => true],
        ];

        $result = $this->service->processBankWithdrawal($data);

        // Assert that proper withdrawal info was returned
        $this->assertArrayHasKey('reference', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals($data['reference'], $result['reference']);
        $this->assertEquals('processing', $result['status']);

        // Verify log was called with sandbox metadata
        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Processing sandbox bank withdrawal', Mockery::on(function ($arg) use ($data) {
                return $arg['environment'] === 'sandbox'
                    && $arg['account_uuid'] === $data['account_uuid']
                    && $arg['amount'] === $data['amount'];
            }));
    }

    public function test_sandbox_service_returns_sandbox_prefixed_ids(): void
    {
        Log::spy();

        // Test Stripe deposit
        $stripeData = [
            'account_uuid'        => $this->account->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
            'reference'           => 'STRIPE-TEST-' . uniqid(),
            'external_reference'  => 'test_pi_123',
            'payment_method'      => 'card',
            'payment_method_type' => 'card',
        ];

        $stripeResult = $this->service->processStripeDeposit($stripeData);
        $this->assertStringStartsWith('sandbox_txn_', $stripeResult);

        // Test bank withdrawal
        $withdrawalData = [
            'account_uuid'        => $this->account->uuid,
            'amount'              => 3000,
            'currency'            => 'GBP',
            'reference'           => 'WD-TEST-' . uniqid(),
            'bank_name'           => 'Test Bank',
            'account_number'      => '12345678',
            'account_holder_name' => 'Test User',
        ];

        $withdrawalResult = $this->service->processBankWithdrawal($withdrawalData);
        $this->assertArrayHasKey('reference', $withdrawalResult);
        $this->assertEquals($withdrawalData['reference'], $withdrawalResult['reference']);
        $this->assertEquals('processing', $withdrawalResult['status']);
    }

    public function test_sandbox_service_preserves_original_metadata(): void
    {
        Log::spy();

        $originalMetadata = [
            'user_data'    => 'important',
            'custom_field' => 'value',
            'nested'       => ['data' => 'preserved'],
        ];

        $data = [
            'account_uuid'        => $this->account->uuid,
            'amount'              => 1500,
            'currency'            => 'USD',
            'reference'           => 'META-TEST-' . uniqid(),
            'external_reference'  => 'test_pi_456',
            'payment_method'      => 'card',
            'payment_method_type' => 'card',
            'metadata'            => $originalMetadata,
        ];

        $this->service->processStripeDeposit($data);

        // We can't directly verify the workflow data without mocking it,
        // but we can verify the log contains the merged metadata
        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Processing sandbox Stripe deposit', Mockery::on(function ($arg) {
                // The metadata in the log doesn't include the merged data,
                // but the original metadata should be present
                return isset($arg['metadata'])
                    && $arg['metadata']['user_data'] === 'important'
                    && $arg['metadata']['custom_field'] === 'value'
                    && $arg['metadata']['nested']['data'] === 'preserved';
            }));
    }
}
