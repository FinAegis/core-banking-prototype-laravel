<?php

namespace Tests\Domain\Account\Aggregates;

use App\Domain\Account\Aggregates\AssetTransactionAggregate;
use App\Domain\Account\Events\AssetBalanceAdded;
use App\Domain\Account\Events\AssetBalanceSubtracted;
use App\Domain\Account\Events\AccountLimitHit;
use App\Domain\Account\Events\TransactionThresholdReached;
use App\Domain\Account\Exceptions\NotEnoughFunds;
use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;
use Tests\TestCase;
use Illuminate\Support\Str;

class AssetTransactionAggregateTest extends TestCase
{

    protected string $accountUuid;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->accountUuid = (string) Str::uuid();
        
        // Create account record for projectors to find
        \App\Models\Account::factory()->create([
            'uuid' => $this->accountUuid,
        ]);
    }

    #[Test]
    public function it_is_an_aggregate_root()
    {
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        expect($aggregate)->toBeInstanceOf(AggregateRoot::class);
        expect($aggregate)->toBeInstanceOf(AssetTransactionAggregate::class);
    }

    #[Test]
    public function it_can_add_asset_balance()
    {
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        $aggregate->credit('USD', 5000);
        
        $events = $aggregate->getRecordedEvents();
        expect($events)->toHaveCount(1);
        expect($events[0])->toBeInstanceOf(AssetBalanceAdded::class);
        expect($events[0]->assetCode)->toBe('USD');
        expect($events[0]->amount)->toBe(5000);
    }

    #[Test]
    public function it_tracks_asset_balances_separately()
    {
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        $aggregate->credit('USD', 5000);
        $aggregate->credit('EUR', 3000);
        $aggregate->credit('BTC', 100000000); // 1 BTC in satoshis
        
        $aggregate->persist();
        
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        expect($aggregate->getBalance('USD'))->toBe(5000);
        expect($aggregate->getBalance('EUR'))->toBe(3000);
        expect($aggregate->getBalance('BTC'))->toBe(100000000);
    }

    #[Test]
    public function it_can_subtract_asset_balance()
    {
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        $aggregate->credit('USD', 10000);
        $aggregate->persist();
        
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        $aggregate->debit('USD', 3000);
        
        $events = $aggregate->getRecordedEvents();
        expect($events)->toHaveCount(1);
        expect($events[0])->toBeInstanceOf(AssetBalanceSubtracted::class);
        expect($events[0]->assetCode)->toBe('USD');
        expect($events[0]->amount)->toBe(3000);
    }

    #[Test]
    public function it_throws_exception_when_insufficient_funds()
    {
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        $aggregate->credit('USD', 1000);
        $aggregate->persist();
        
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        $this->expectException(NotEnoughFunds::class);
        $aggregate->debit('USD', 2000);
    }

    #[Test]
    public function it_throws_exception_when_debiting_from_non_existent_asset()
    {
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        $this->expectException(NotEnoughFunds::class);
        $aggregate->debit('XYZ', 100);
    }

    #[Test]
    public function it_records_account_limit_hit_event_before_throwing_exception()
    {
        AssetTransactionAggregate::fake($this->accountUuid)
            ->given([
                new AssetBalanceAdded(
                    assetCode: 'USD',
                    amount: 1000,
                    hash: new Hash(hash('sha3-512', 'USD:1000:' . time()))
                )
            ])
            ->when(function (AssetTransactionAggregate $aggregate): void {
                $this->expectException(NotEnoughFunds::class);
                $aggregate->debit('USD', 2000);
            })
            ->assertApplied([
                new AccountLimitHit()
            ])
            ->assertNotRecorded(AssetBalanceSubtracted::class);
    }

    #[Test]
    public function it_triggers_threshold_event_after_many_transactions()
    {
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        // Credit 999 times
        for ($i = 0; $i < 999; $i++) {
            $aggregate->credit('USD', 1);
        }
        
        expect($aggregate->getRecordedEvents())->toHaveCount(999);
        
        // The 1000th transaction should trigger threshold event
        $aggregate->credit('USD', 1);
        
        $events = $aggregate->getRecordedEvents();
        expect($events)->toHaveCount(1001); // 1000 credits + 1 threshold event
        expect($events[1000])->toBeInstanceOf(TransactionThresholdReached::class);
    }

    #[Test]
    public function it_resets_count_after_threshold_reached()
    {
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        // Credit 1000 times to trigger threshold
        for ($i = 0; $i < 1000; $i++) {
            $aggregate->credit('USD', 1);
        }
        
        $aggregate->persist();
        
        // Retrieve and add one more transaction
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        $aggregate->credit('USD', 1);
        
        // Should only have 1 new event, not trigger threshold again
        $events = $aggregate->getRecordedEvents();
        expect($events)->toHaveCount(1);
        expect($events[0])->toBeInstanceOf(AssetBalanceAdded::class);
    }

    #[Test]
    public function it_can_handle_multiple_asset_operations()
    {
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        // Credit multiple assets
        $aggregate->credit('USD', 10000);
        $aggregate->credit('EUR', 5000);
        $aggregate->credit('BTC', 50000000); // 0.5 BTC
        
        $aggregate->persist();
        
        // Debit from different assets
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        $aggregate->debit('USD', 2500);
        $aggregate->debit('EUR', 1000);
        $aggregate->debit('BTC', 10000000); // 0.1 BTC
        
        $aggregate->persist();
        
        // Verify final balances
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        expect($aggregate->getBalance('USD'))->toBe(7500);
        expect($aggregate->getBalance('EUR'))->toBe(4000);
        expect($aggregate->getBalance('BTC'))->toBe(40000000);
    }

    #[Test]
    public function it_returns_all_asset_balances()
    {
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        $aggregate->credit('USD', 10000);
        $aggregate->credit('EUR', 5000);
        $aggregate->credit('GBP', 7500);
        
        $aggregate->persist();
        
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        $balances = $aggregate->getAllBalances();
        
        expect($balances)->toBe([
            'USD' => 10000,
            'EUR' => 5000,
            'GBP' => 7500,
        ]);
    }

    #[Test]
    public function it_returns_zero_for_non_existent_asset_balance()
    {
        $aggregate = AssetTransactionAggregate::retrieve($this->accountUuid);
        
        expect($aggregate->getBalance('XYZ'))->toBe(0);
    }
}