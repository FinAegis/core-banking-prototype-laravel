<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Payment\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Payment\Services\DemoPaymentService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private DemoPaymentService $service;

    protected User $user;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DemoPaymentService();

        // Create test user and account
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'uuid'      => 'test-account-uuid',
        ]);
    }

    public function test_process_stripe_deposit(): void
    {
        $data = [
            'account_uuid'        => $this->account->uuid,
            'amount'              => 10000, // $100
            'currency'            => 'USD',
            'reference'           => 'TEST-REF-' . uniqid(),
            'external_reference'  => 'stripe_pi_test123',
            'payment_method'      => 'card',
            'payment_method_type' => 'card',
            'metadata'            => ['test' => true],
        ];

        $result = $this->service->processStripeDeposit($data);

        // Assert that a demo payment intent ID was returned
        $this->assertStringStartsWith('demo_pi_', $result);

        // Assert that the transaction was created
        $transaction = TransactionProjection::where('reference', $data['reference'])->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('deposit', $transaction->type);
        $this->assertEquals($data['amount'], $transaction->amount);
        $this->assertEquals($data['currency'], $transaction->asset_code);
        $this->assertEquals('completed', $transaction->status);
    }

    public function test_process_bank_withdrawal(): void
    {
        $data = [
            'account_uuid'        => $this->account->uuid,
            'amount'              => 5000, // $50
            'currency'            => 'USD',
            'reference'           => 'TEST-WD-' . uniqid(),
            'bank_name'           => 'Test Bank',
            'account_number'      => '1234567890',
            'account_holder_name' => 'Test User',
            'metadata'            => ['test' => true],
        ];

        $result = $this->service->processBankWithdrawal($data);

        // Assert that a withdrawal reference was returned
        $this->assertArrayHasKey('reference', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertStringStartsWith('demo_wd_', $result['reference']);
        $this->assertEquals('completed', $result['status']);

        // Assert that the transaction was created with negative amount
        $transaction = TransactionProjection::where('reference', $data['reference'])->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('withdrawal', $transaction->type);
        $this->assertEquals(-$data['amount'], $transaction->amount);
        $this->assertEquals($data['currency'], $transaction->asset_code);
        $this->assertEquals('completed', $transaction->status);
    }

    public function test_process_openbanking_deposit(): void
    {
        $data = [
            'account_uuid' => $this->account->uuid,
            'amount'       => 20000, // $200
            'currency'     => 'EUR',
            'reference'    => 'TEST-OB-' . uniqid(),
            'bank_name'    => 'Demo European Bank',
            'metadata'     => ['test' => true],
        ];

        $result = $this->service->processOpenBankingDeposit($data);

        // Assert that an OpenBanking reference was returned
        $this->assertStringStartsWith('demo_ob_', $result);

        // Assert that the transaction was created
        $transaction = TransactionProjection::where('reference', $data['reference'])->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('deposit', $transaction->type);
        $this->assertEquals($data['amount'], $transaction->amount);
        $this->assertEquals($data['currency'], $transaction->asset_code);
        $this->assertEquals('completed', $transaction->status);

        // Check metadata
        $metadata = $transaction->metadata;
        $this->assertTrue($metadata['demo_mode'] ?? false);
        $this->assertEquals('Demo European Bank', $metadata['bank_name'] ?? '');
    }

    public function test_demo_service_does_not_call_external_apis(): void
    {
        // This test verifies that the demo service doesn't make external calls
        // by checking that it completes instantly without network delays

        $startTime = microtime(true);

        $data = [
            'account_uuid'        => $this->account->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
            'reference'           => 'PERF-TEST-' . uniqid(),
            'external_reference'  => 'test_pi_123',
            'payment_method'      => 'card',
            'payment_method_type' => 'card',
        ];

        $this->service->processStripeDeposit($data);

        $executionTime = microtime(true) - $startTime;

        // Demo service should complete in under 1 second
        // Real Stripe API calls would take 1-5 seconds
        $this->assertLessThan(1.0, $executionTime);
    }
}
