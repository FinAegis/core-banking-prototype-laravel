<?php

namespace Tests\Unit\Domain\Account\Actions;

use App\Domain\Account\Actions\DebitAccount;
use App\Domain\Account\DataObjects\Hash;
use App\Domain\Account\Events\AssetBalanceSubtracted;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Repositories\AccountRepository;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class DebitAccountTest extends DomainTestCase
{
    use RefreshDatabase;

    private DebitAccount $action;

    private AccountRepository $accountRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountRepository = new AccountRepository(new Account());
        $this->action = new DebitAccount($this->accountRepository);
    }

    private function createAssetBalanceSubtractedMock(string $aggregateRootUuid, string $assetCode, int $amount)
    {
        // Create a test double that extends the event class
        return new class ($assetCode, $amount, Hash::fromData("test-{$assetCode}-{$amount}"), ['test' => true], $aggregateRootUuid) extends AssetBalanceSubtracted {
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
    public function test_debits_existing_balance(): void
    {
        // Create account
        $account = Account::factory()->create([
            'uuid' => 'account-123',
            'name' => 'Test Account',
        ]);

        // Create existing balance
        AccountBalance::create([
            'account_uuid' => 'account-123',
            'asset_code'   => 'USD',
            'balance'      => 5000, // $50.00
        ]);

        // Repository will find the account by UUID

        // Create event
        $event = $this->createAssetBalanceSubtractedMock('account-123', 'USD', 2000); // $20.00

        // Execute
        $result = $this->action->__invoke($event);

        // Assert
        $this->assertInstanceOf(Account::class, $result);

        $updatedBalance = AccountBalance::where('account_uuid', 'account-123')
            ->where('asset_code', 'USD')
            ->first();

        $this->assertEquals(3000, $updatedBalance->balance); // $30.00
    }

    #[Test]
    public function test_throws_exception_if_balance_not_found(): void
    {
        // Create account
        $account = Account::factory()->create([
            'uuid' => 'account-456',
            'name' => 'No Balance Account',
        ]);

        // Repository will find the account by UUID

        // Create event
        $event = $this->createAssetBalanceSubtractedMock('account-456', 'EUR', 1000);

        // Assert exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Asset balance not found for EUR');

        // Execute
        $this->action->__invoke($event);
    }

    #[Test]
    public function test_throws_exception_if_insufficient_balance(): void
    {
        // Create account
        $account = Account::factory()->create([
            'uuid' => 'account-789',
            'name' => 'Low Balance Account',
        ]);

        // Create existing balance
        AccountBalance::create([
            'account_uuid' => 'account-789',
            'asset_code'   => 'USD',
            'balance'      => 1000, // $10.00
        ]);

        // Repository will find the account by UUID

        // Create event that would overdraw
        $event = $this->createAssetBalanceSubtractedMock('account-789', 'USD', 2000); // $20.00

        // Assert exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient balance for USD');

        // Execute
        $this->action->__invoke($event);
    }

    #[Test]
    public function test_handles_exact_balance_debit(): void
    {
        // Create account
        $account = Account::factory()->create([
            'uuid' => 'exact-balance-account',
            'name' => 'Exact Balance Account',
        ]);

        // Create existing balance
        AccountBalance::create([
            'account_uuid' => 'exact-balance-account',
            'asset_code'   => 'BTC',
            'balance'      => 100000000, // 1 BTC
        ]);

        // Repository will find the account by UUID

        // Create event for exact balance
        $event = $this->createAssetBalanceSubtractedMock('exact-balance-account', 'BTC', 100000000); // 1 BTC

        // Execute
        $result = $this->action->__invoke($event);

        // Assert
        $updatedBalance = AccountBalance::where('account_uuid', 'exact-balance-account')
            ->where('asset_code', 'BTC')
            ->first();

        $this->assertEquals(0, $updatedBalance->balance);
    }

    #[Test]
    public function test_handles_multiple_debits(): void
    {
        // Create account
        $account = Account::factory()->create([
            'uuid' => 'multi-debit-account',
            'name' => 'Multi Debit Account',
        ]);

        // Create existing balance
        AccountBalance::create([
            'account_uuid' => 'multi-debit-account',
            'asset_code'   => 'EUR',
            'balance'      => 10000, // €100.00
        ]);

        // Repository will find the account by UUID

        // First debit
        $event1 = $this->createAssetBalanceSubtractedMock('multi-debit-account', 'EUR', 3000); // €30.00

        $this->action->__invoke($event1);

        // Second debit
        $event2 = $this->createAssetBalanceSubtractedMock('multi-debit-account', 'EUR', 2000); // €20.00

        $this->action->__invoke($event2);

        // Assert
        $balance = AccountBalance::where('account_uuid', 'multi-debit-account')
            ->where('asset_code', 'EUR')
            ->first();

        $this->assertEquals(5000, $balance->balance); // €50.00 remaining
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
