<?php

namespace Tests\Unit\Domain\Account\Actions;

use App\Domain\Account\Actions\CreditAccount;
use App\Domain\Account\DataObjects\Hash;
use App\Domain\Account\Events\AssetBalanceAdded;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Repositories\AccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class CreditAccountTest extends DomainTestCase
{
    use RefreshDatabase;

    private CreditAccount $action;

    private AccountRepository $accountRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountRepository = new AccountRepository(new Account());
        $this->action = new CreditAccount($this->accountRepository);
    }

    private function createAssetBalanceAddedMock(string $aggregateRootUuid, string $assetCode, int $amount)
    {
        // Create a test double that extends the event class
        return new class ($assetCode, $amount, Hash::fromData("test-{$assetCode}-{$amount}"), ['test' => true], $aggregateRootUuid) extends AssetBalanceAdded {
            private string $testAggregateRootUuid;

            public function __construct(
                string $assetCode,
                int $amount,
                Hash $hash,
                ?array $metadata,
                string $aggregateRootUuid
            ) {
                parent::__construct($assetCode, $amount, $hash, $metadata);
                $this->testAggregateRootUuid = $aggregateRootUuid;
            }

            public function aggregateRootUuid(): string
            {
                return $this->testAggregateRootUuid;
            }
        };
    }

    #[Test]
    public function test_credits_existing_balance(): void
    {
        // Create account
        $account = Account::factory()->create([
            'uuid' => 'account-123',
            'name' => 'Test Account',
        ]);

        // Create existing balance
        $balance = AccountBalance::create([
            'account_uuid' => 'account-123',
            'asset_code'   => 'USD',
            'balance'      => 1000, // $10.00
        ]);

        // Repository will find the account by UUID

        // Create event
        $event = $this->createAssetBalanceAddedMock('account-123', 'USD', 500); // $5.00

        // Execute
        $result = $this->action->__invoke($event);

        // Assert
        $this->assertInstanceOf(Account::class, $result);

        $updatedBalance = AccountBalance::where('account_uuid', 'account-123')
            ->where('asset_code', 'USD')
            ->first();

        $this->assertEquals(1500, $updatedBalance->balance); // $15.00
    }

    #[Test]
    public function test_creates_new_balance_if_not_exists(): void
    {
        // Create account
        $account = Account::factory()->create([
            'uuid' => 'account-456',
            'name' => 'New Account',
        ]);

        // Repository will find the account by UUID

        // Create event
        $event = $this->createAssetBalanceAddedMock('account-456', 'EUR', 2500); // €25.00

        // Execute
        $result = $this->action->__invoke($event);

        // Assert
        $this->assertInstanceOf(Account::class, $result);

        $balance = AccountBalance::where('account_uuid', 'account-456')
            ->where('asset_code', 'EUR')
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(2500, $balance->balance); // €25.00
    }

    #[Test]
    public function test_handles_multiple_credits_to_same_account(): void
    {
        // Create account
        $account = Account::factory()->create([
            'uuid' => 'account-789',
            'name' => 'Multi Credit Account',
        ]);

        // Repository will find the account by UUID

        // First credit
        $event1 = $this->createAssetBalanceAddedMock('account-789', 'BTC', 100000); // 0.001 BTC

        $this->action->__invoke($event1);

        // Second credit
        $event2 = $this->createAssetBalanceAddedMock('account-789', 'BTC', 50000); // 0.0005 BTC

        $this->action->__invoke($event2);

        // Assert
        $balance = AccountBalance::where('account_uuid', 'account-789')
            ->where('asset_code', 'BTC')
            ->first();

        $this->assertEquals(150000, $balance->balance); // 0.0015 BTC
    }

    #[Test]
    public function test_handles_different_asset_codes(): void
    {
        // Create account
        $account = Account::factory()->create([
            'uuid' => 'multi-asset-account',
            'name' => 'Multi Asset Account',
        ]);

        // Repository will find the account by UUID

        // Credit USD
        $usdEvent = $this->createAssetBalanceAddedMock('multi-asset-account', 'USD', 10000); // $100.00

        $this->action->__invoke($usdEvent);

        // Credit EUR
        $eurEvent = $this->createAssetBalanceAddedMock('multi-asset-account', 'EUR', 5000); // €50.00

        $this->action->__invoke($eurEvent);

        // Assert
        $usdBalance = AccountBalance::where('account_uuid', 'multi-asset-account')
            ->where('asset_code', 'USD')
            ->first();
        $eurBalance = AccountBalance::where('account_uuid', 'multi-asset-account')
            ->where('asset_code', 'EUR')
            ->first();

        $this->assertEquals(10000, $usdBalance->balance);
        $this->assertEquals(5000, $eurBalance->balance);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
