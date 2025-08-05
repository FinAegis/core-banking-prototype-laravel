<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Custodian\Connectors;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\Connectors\DemoBankConnector;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DemoBankConnectorTest extends TestCase
{
    use RefreshDatabase;

    private DemoBankConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock config for demo bank
        $config = [
            'base_url' => 'https://demo.bank.com',
            'timeout'  => 30,
        ];

        $this->connector = new DemoBankConnector($config);
    }

    public function test_get_name_returns_demo_bank(): void
    {
        $this->assertEquals('demo_bank', $this->connector->getName());
    }

    public function test_is_available_in_demo_mode(): void
    {
        Config::set('demo.mode', true);
        $this->assertTrue($this->connector->isAvailable());

        Config::set('demo.mode', false);
        Config::set('demo.sandbox.enabled', true);
        $this->assertTrue($this->connector->isAvailable());

        Config::set('demo.mode', false);
        Config::set('demo.sandbox.enabled', false);
        $this->assertFalse($this->connector->isAvailable());
    }

    public function test_supported_assets(): void
    {
        $supportedAssets = $this->connector->getSupportedAssets();

        $this->assertContains('USD', $supportedAssets);
        $this->assertContains('EUR', $supportedAssets);
        $this->assertContains('GBP', $supportedAssets);
        $this->assertContains('CHF', $supportedAssets);
        $this->assertContains('JPY', $supportedAssets);
        $this->assertContains('CAD', $supportedAssets);
        $this->assertContains('AUD', $supportedAssets);
    }

    public function test_validate_account_accepts_any_non_empty_string(): void
    {
        Log::spy();

        $this->assertTrue($this->connector->validateAccount('123456789'));
        $this->assertTrue($this->connector->validateAccount('IBAN123456'));
        $this->assertTrue($this->connector->validateAccount('demo-account'));

        $this->assertFalse($this->connector->validateAccount(''));

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('info')
            ->times(4)
            ->with('Demo bank account validation', \Mockery::any());
    }

    public function test_get_account_info_returns_demo_data(): void
    {
        Log::spy();

        $accountInfo = $this->connector->getAccountInfo('demo-123');

        $this->assertEquals('demo-123', $accountInfo->accountId);
        $this->assertEquals('Demo Account', $accountInfo->name);
        $this->assertEquals('checking', $accountInfo->type);
        $this->assertEquals('USD', $accountInfo->currency);
        $this->assertEquals('active', $accountInfo->status);
        $this->assertTrue($accountInfo->metadata['demo_mode']);
        $this->assertArrayHasKey('created_at', $accountInfo->metadata);

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Demo bank account info request', ['account_id' => 'demo-123']);
    }

    public function test_get_balance_returns_default_balance(): void
    {
        Log::spy();

        $balance = $this->connector->getBalance('demo-account', 'USD');

        // Default balance should be $10,000 (1,000,000 cents)
        $this->assertEquals(1000000, $balance->getAmount());

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Demo bank balance request', [
                'account_id' => 'demo-account',
                'asset_code' => 'USD',
            ]);
    }

    public function test_get_balance_uses_session_stored_balance(): void
    {
        // Set custom balance in session
        session()->put('demo_bank_balances', [
            'custom-account' => [
                'USD' => 5000, // $50
            ],
        ]);

        $balance = $this->connector->getBalance('custom-account', 'USD');

        $this->assertEquals(5000, $balance->getAmount());
    }

    public function test_initiate_transfer_completes_instantly(): void
    {
        Log::spy();

        $transferRequest = new TransferRequest(
            fromAccount: 'sender-account',
            toAccount: 'receiver-account',
            amount: new Money(10000), // $100
            assetCode: 'USD',
            reference: 'TEST-REF-123',
            description: 'Test transfer'
        );

        $receipt = $this->connector->initiateTransfer($transferRequest);

        // Assert receipt details
        $this->assertStringStartsWith('demo_txn_', $receipt->id);
        $this->assertEquals('completed', $receipt->status);
        $this->assertEquals(10000, $receipt->amount);
        $this->assertEquals('USD', $receipt->assetCode);
        $this->assertEquals('sender-account', $receipt->fromAccount);
        $this->assertEquals('receiver-account', $receipt->toAccount);
        $this->assertEquals('TEST-REF-123', $receipt->reference);
        $this->assertEquals('Test transfer', $receipt->metadata['description']);
        $this->assertTrue($receipt->metadata['demo_mode']);
        $this->assertTrue($receipt->metadata['instant_transfer']);
        $this->assertArrayHasKey('processing_time_ms', $receipt->metadata);

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Demo bank transfer initiated', \Mockery::any());
    }

    public function test_initiate_transfer_updates_session_balances(): void
    {
        // Set initial balances
        session()->put('demo_bank_balances', [
            'sender'   => ['USD' => 50000], // $500
            'receiver' => ['USD' => 10000], // $100
        ]);

        $transferRequest = new TransferRequest(
            fromAccount: 'sender',
            toAccount: 'receiver',
            amount: new Money(20000), // $200
            assetCode: 'USD',
            reference: 'TEST-REF',
            description: 'Balance test'
        );

        $this->connector->initiateTransfer($transferRequest);

        // Check updated balances
        $balances = session()->get('demo_bank_balances');
        $this->assertEquals(30000, $balances['sender']['USD']); // $300 remaining
        $this->assertEquals(30000, $balances['receiver']['USD']); // $300 total
    }

    public function test_get_transaction_status_always_returns_completed(): void
    {
        Log::spy();

        $receipt = $this->connector->getTransactionStatus('demo_txn_12345');

        $this->assertEquals('demo_txn_12345', $receipt->id);
        $this->assertEquals('completed', $receipt->status);
        $this->assertTrue($receipt->metadata['demo_mode']);

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Demo bank transaction status request', ['transaction_id' => 'demo_txn_12345']);
    }

    public function test_cancel_transaction_always_returns_false(): void
    {
        Log::spy();

        $result = $this->connector->cancelTransaction('demo_txn_12345');

        $this->assertFalse($result);

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Demo bank transaction cancellation', ['transaction_id' => 'demo_txn_12345']);
    }

    public function test_get_transaction_history_returns_sample_data(): void
    {
        Log::spy();

        $history = $this->connector->getTransactionHistory('demo-account', 3, 0);

        $this->assertCount(3, $history);

        foreach ($history as $index => $transaction) {
            $this->assertStringStartsWith('demo_txn_', $transaction['id']);
            $this->assertContains($transaction['type'], ['credit', 'debit']);
            $this->assertGreaterThanOrEqual(1000, $transaction['amount']);
            $this->assertLessThanOrEqual(50000, $transaction['amount']);
            $this->assertEquals('USD', $transaction['currency']);
            $this->assertEquals('completed', $transaction['status']);
            $this->assertStringContainsString('Demo transaction #', $transaction['description']);
            $this->assertArrayHasKey('date', $transaction);
        }

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Demo bank transaction history request', [
                'account_id' => 'demo-account',
                'limit'      => 3,
                'offset'     => 0,
            ]);
    }

    public function test_transaction_history_respects_limit(): void
    {
        $history = $this->connector->getTransactionHistory('demo-account', 10, 0);

        // Should return maximum of 5 transactions even if more requested
        $this->assertCount(5, $history);
    }

    public function test_demo_connector_does_not_make_external_calls(): void
    {
        // This test verifies that all operations complete without network delays
        $startTime = microtime(true);

        // Run multiple operations
        $this->connector->validateAccount('test-account');
        $this->connector->getAccountInfo('test-account');
        $this->connector->getBalance('test-account', 'USD');
        $this->connector->getTransactionHistory('test-account');

        $executionTime = microtime(true) - $startTime;

        // All operations should complete in under 0.5 seconds
        // Real API calls would take much longer
        $this->assertLessThan(0.5, $executionTime);
    }
}
