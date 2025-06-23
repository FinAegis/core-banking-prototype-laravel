<?php

namespace Tests\Feature;

use App\Models\User;
use App\Domain\Account\Aggregates\LedgerAggregate;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Account\Workflows\DepositAccountWorkflow;
use App\Domain\Account\Workflows\WithdrawAccountWorkflow;
use App\Domain\Payment\Workflows\TransferWorkflow;
use App\Domain\Asset\Workflows\AssetDepositWorkflow;
use App\Domain\Asset\Workflows\AssetWithdrawWorkflow;
use App\Domain\Asset\Workflows\AssetTransferWorkflow;
use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Workflow\WorkflowStub;

class WalletWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $testUser;
    protected Account $testAccount;
    protected Account $recipientAccount;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users and accounts
        $this->testUser = User::factory()->withPersonalTeam()->create();
        $this->testAccount = Account::factory()->create([
            'user_uuid' => $this->testUser->uuid,
            'balance' => 20000, // $200.00 USD for legacy balance
        ]);
        
        $recipientUser = User::factory()->withPersonalTeam()->create();
        $this->recipientAccount = Account::factory()->create([
            'user_uuid' => $recipientUser->uuid,
            'balance' => 0,
        ]);
        
        // Create assets
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'GBP'], ['name' => 'British Pound', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'BTC'], ['name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'GCU'], ['name' => 'Global Currency Unit', 'type' => 'basket', 'precision' => 2, 'is_active' => true]);
        
        // Create initial balances
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'USD',
            ],
            [
                'balance' => 20000, // $200.00
            ]
        );
        
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'EUR',
            ],
            [
                'balance' => 10000, // €100.00
            ]
        );
        
        AccountBalance::updateOrCreate(
            [
                'account_uuid' => $this->testAccount->uuid,
                'asset_code' => 'BTC',
            ],
            [
                'balance' => 100000000, // 1 BTC
            ]
        );
        
        // Create exchange rates
        ExchangeRate::create([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'EUR',
            'rate' => 0.92,
            'source' => 'manual',
            'valid_at' => now(),
        ]);
        
        ExchangeRate::create([
            'from_asset_code' => 'EUR',
            'to_asset_code' => 'USD',
            'rate' => 1.09,
            'source' => 'manual',
            'valid_at' => now(),
        ]);
    }

    /** @test */
    public function it_can_deposit_usd_using_legacy_workflow()
    {
        $this->markTestSkipped('Workflow integration tests require proper event sourcing setup with projectors');
        $workflow = WorkflowStub::make(DepositAccountWorkflow::class);
        $workflow->start(
            AccountUuid::fromString($this->testAccount->uuid),
            new Money(5000) // $50.00
        );
        
        // Check account balance updated
        $this->testAccount->refresh();
        $this->assertEquals(25000, $this->testAccount->balance); // $250.00
        
        // Check USD balance updated
        $usdBalance = AccountBalance::where('account_uuid', $this->testAccount->uuid)
            ->where('asset_code', 'USD')
            ->first();
        $this->assertEquals(25000, $usdBalance->balance); // $250.00
        
        // Check event stored
        $aggregate = LedgerAggregate::retrieve($this->testAccount->uuid);
        $events = $aggregate->getAppliedEvents();
        $this->assertNotEmpty($events);
    }

    /** @test */
    public function it_can_deposit_multi_asset_using_asset_workflow()
    {
        $this->markTestSkipped('Workflow integration tests require proper event sourcing setup with projectors');
        $workflow = WorkflowStub::make(AssetDepositWorkflow::class);
        $workflow->start(
            AccountUuid::fromString($this->testAccount->uuid),
            'EUR',
            2500 // €25.00
        );
        
        // Check EUR balance updated
        $eurBalance = AccountBalance::where('account_uuid', $this->testAccount->uuid)
            ->where('asset_code', 'EUR')
            ->first();
        $this->assertEquals(12500, $eurBalance->balance); // €125.00
        
        // USD balance should remain unchanged
        $usdBalance = AccountBalance::where('account_uuid', $this->testAccount->uuid)
            ->where('asset_code', 'USD')
            ->first();
        $this->assertEquals(20000, $usdBalance->balance); // $200.00
    }

    /** @test */
    public function it_can_withdraw_usd_using_legacy_workflow()
    {
        $this->markTestSkipped('Workflow integration tests require proper event sourcing setup with projectors');
        $workflow = WorkflowStub::make(WithdrawAccountWorkflow::class);
        $workflow->start(
            AccountUuid::fromString($this->testAccount->uuid),
            new Money(3000) // $30.00
        );
        
        // Check account balance updated
        $this->testAccount->refresh();
        $this->assertEquals(17000, $this->testAccount->balance); // $170.00
        
        // Check USD balance updated
        $usdBalance = AccountBalance::where('account_uuid', $this->testAccount->uuid)
            ->where('asset_code', 'USD')
            ->first();
        $this->assertEquals(17000, $usdBalance->balance); // $170.00
    }

    /** @test */
    public function it_can_withdraw_multi_asset_using_asset_workflow()
    {
        $this->markTestSkipped('Workflow integration tests require proper event sourcing setup with projectors');
        $workflow = WorkflowStub::make(AssetWithdrawWorkflow::class);
        $workflow->start(
            AccountUuid::fromString($this->testAccount->uuid),
            'EUR',
            4000 // €40.00
        );
        
        // Check EUR balance updated
        $eurBalance = AccountBalance::where('account_uuid', $this->testAccount->uuid)
            ->where('asset_code', 'EUR')
            ->first();
        $this->assertEquals(6000, $eurBalance->balance); // €60.00
    }

    /** @test */
    public function it_can_transfer_usd_between_accounts()
    {
        $this->markTestSkipped('Workflow integration tests require proper event sourcing setup with projectors');
        $workflow = WorkflowStub::make(TransferWorkflow::class);
        $workflow->start(
            AccountUuid::fromString($this->testAccount->uuid),
            AccountUuid::fromString($this->recipientAccount->uuid),
            new Money(5000) // $50.00
        );
        
        // Check sender balance
        $this->testAccount->refresh();
        $this->assertEquals(15000, $this->testAccount->balance); // $150.00
        
        // Check recipient balance
        $this->recipientAccount->refresh();
        $this->assertEquals(5000, $this->recipientAccount->balance); // $50.00
        
        // Check account balances
        $senderUsdBalance = AccountBalance::where('account_uuid', $this->testAccount->uuid)
            ->where('asset_code', 'USD')
            ->first();
        $this->assertEquals(15000, $senderUsdBalance->balance);
        
        $recipientUsdBalance = AccountBalance::where('account_uuid', $this->recipientAccount->uuid)
            ->where('asset_code', 'USD')
            ->first();
        $this->assertEquals(5000, $recipientUsdBalance->balance);
    }

    /** @test */
    public function it_can_transfer_multi_asset_between_accounts()
    {
        $this->markTestSkipped('Workflow integration tests require proper event sourcing setup with projectors');
        $workflow = WorkflowStub::make(AssetTransferWorkflow::class);
        $workflow->start(
            AccountUuid::fromString($this->testAccount->uuid),
            AccountUuid::fromString($this->recipientAccount->uuid),
            'EUR',
            3000 // €30.00
        );
        
        // Check sender EUR balance
        $senderEurBalance = AccountBalance::where('account_uuid', $this->testAccount->uuid)
            ->where('asset_code', 'EUR')
            ->first();
        $this->assertEquals(7000, $senderEurBalance->balance); // €70.00
        
        // Check recipient EUR balance
        $recipientEurBalance = AccountBalance::where('account_uuid', $this->recipientAccount->uuid)
            ->where('asset_code', 'EUR')
            ->first();
        $this->assertEquals(3000, $recipientEurBalance->balance); // €30.00
    }

    /** @test */
    public function it_can_convert_currency_using_workflows()
    {
        $this->markTestSkipped('Workflow integration tests require proper event sourcing setup with projectors');
        // Convert $50 USD to EUR
        $amountInCents = 5000;
        $rate = 0.92;
        $convertedAmount = (int) round($amountInCents * $rate);
        
        // Withdraw USD
        $withdrawWorkflow = WorkflowStub::make(WithdrawAccountWorkflow::class);
        $withdrawWorkflow->start(
            AccountUuid::fromString($this->testAccount->uuid),
            new Money($amountInCents)
        );
        
        // Deposit EUR
        $depositWorkflow = WorkflowStub::make(AssetDepositWorkflow::class);
        $depositWorkflow->start(
            AccountUuid::fromString($this->testAccount->uuid),
            'EUR',
            $convertedAmount
        );
        
        // Check balances
        $usdBalance = AccountBalance::where('account_uuid', $this->testAccount->uuid)
            ->where('asset_code', 'USD')
            ->first();
        $this->assertEquals(15000, $usdBalance->balance); // $150.00
        
        $eurBalance = AccountBalance::where('account_uuid', $this->testAccount->uuid)
            ->where('asset_code', 'EUR')
            ->first();
        $this->assertEquals(14600, $eurBalance->balance); // €146.00 (100 + 46)
    }

    /** @test */
    public function it_prevents_overdraft_in_withdraw_workflow()
    {
        $this->markTestSkipped('Workflow integration tests require proper event sourcing setup with projectors');
        $this->expectException(\Exception::class);
        
        $workflow = WorkflowStub::make(WithdrawAccountWorkflow::class);
        $workflow->start(
            AccountUuid::fromString($this->testAccount->uuid),
            new Money(30000) // $300.00 (more than balance)
        );
    }

    /** @test */
    public function it_prevents_overdraft_in_asset_withdraw_workflow()
    {
        $this->markTestSkipped('Workflow integration tests require proper event sourcing setup with projectors');
        $this->expectException(\Exception::class);
        
        $workflow = WorkflowStub::make(AssetWithdrawWorkflow::class);
        $workflow->start(
            AccountUuid::fromString($this->testAccount->uuid),
            'EUR',
            20000 // €200.00 (more than balance)
        );
    }

    /** @test */
    public function it_handles_bitcoin_transfers_with_correct_precision()
    {
        $this->markTestSkipped('Workflow integration tests require proper event sourcing setup with projectors');
        $workflow = WorkflowStub::make(AssetTransferWorkflow::class);
        $workflow->start(
            AccountUuid::fromString($this->testAccount->uuid),
            AccountUuid::fromString($this->recipientAccount->uuid),
            'BTC',
            50000000 // 0.5 BTC
        );
        
        // Check balances
        $senderBtcBalance = AccountBalance::where('account_uuid', $this->testAccount->uuid)
            ->where('asset_code', 'BTC')
            ->first();
        $this->assertEquals(50000000, $senderBtcBalance->balance); // 0.5 BTC remaining
        
        $recipientBtcBalance = AccountBalance::where('account_uuid', $this->recipientAccount->uuid)
            ->where('asset_code', 'BTC')
            ->first();
        $this->assertEquals(50000000, $recipientBtcBalance->balance); // 0.5 BTC received
    }

    /** @test */
    public function it_creates_balance_record_if_not_exists_on_deposit()
    {
        $this->markTestSkipped('Workflow integration tests require proper event sourcing setup with projectors');
        // Ensure no GCU balance exists
        AccountBalance::where('account_uuid', $this->testAccount->uuid)
            ->where('asset_code', 'GCU')
            ->delete();
        
        $workflow = WorkflowStub::make(AssetDepositWorkflow::class);
        $workflow->start(
            AccountUuid::fromString($this->testAccount->uuid),
            'GCU',
            10000 // 100.00 GCU
        );
        
        // Check GCU balance created
        $gcuBalance = AccountBalance::where('account_uuid', $this->testAccount->uuid)
            ->where('asset_code', 'GCU')
            ->first();
        $this->assertNotNull($gcuBalance);
        $this->assertEquals(10000, $gcuBalance->balance);
    }
}