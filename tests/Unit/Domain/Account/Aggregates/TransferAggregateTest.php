<?php

namespace Tests\Unit\Domain\Account\Aggregates;

use App\Domain\Account\Aggregates\TransferAggregate;
use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Hash;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Events\MoneyTransferred;
use App\Domain\Account\Events\TransferThresholdReached;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class TransferAggregateTest extends DomainTestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_transfers_money_between_accounts(): void
    {
        $aggregate = TransferAggregate::fake();

        $from = new AccountUuid('from-account-uuid');
        $to = new AccountUuid('to-account-uuid');
        $money = new Money(2500); // $25.00

        $aggregate->transfer($from, $to, $money);

        // Assert that a MoneyTransferred event was recorded
        $eventRecorded = false;
        $aggregate->assertRecorded(function ($event) use ($from, $to, $money, &$eventRecorded) {
            if (
                $event instanceof MoneyTransferred &&
                (string) $event->from === (string) $from &&
                (string) $event->to === (string) $to &&
                $event->money->getAmount() === $money->getAmount()
            ) {
                $eventRecorded = true;

                return true;
            }

            return false;
        });

        $this->assertTrue($eventRecorded, 'MoneyTransferred event should be recorded');
    }

    #[Test]
    public function test_applies_money_transferred_event(): void
    {
        $aggregate = new TransferAggregate();

        $from = new AccountUuid('sender-uuid');
        $to = new AccountUuid('receiver-uuid');
        $money = new Money(5000); // €50.00

        // Set count just below threshold to test
        $aggregate->count = 0;

        // First set the currentHash to a known value
        $reflection = new ReflectionClass($aggregate);
        $currentHashProperty = $reflection->getProperty('currentHash');
        $currentHashProperty->setAccessible(true);
        $currentHashProperty->setValue($aggregate, '');

        // Then generate hash using the same state
        $method = $reflection->getMethod('generateHash');
        $method->setAccessible(true);
        $hash = $method->invoke($aggregate, $money);

        $event = new MoneyTransferred(
            from: $from,
            to: $to,
            money: $money,
            hash: $hash
        );

        $aggregate->applyMoneyTransferred($event);

        $this->assertEquals(1, $aggregate->count);
    }

    #[Test]
    public function test_records_transfer_threshold_reached(): void
    {
        $aggregate = TransferAggregate::fake();

        $from = new AccountUuid('from-uuid');
        $to = new AccountUuid('to-uuid');
        $money = new Money(100);

        // Make COUNT_THRESHOLD transfers
        for ($i = 0; $i < TransferAggregate::COUNT_THRESHOLD; $i++) {
            $aggregate->transfer($from, $to, $money);
        }

        // Verify threshold event was recorded
        $thresholdEventRecorded = false;
        $aggregate->assertRecorded(function ($event) use (&$thresholdEventRecorded) {
            if ($event instanceof TransferThresholdReached) {
                $thresholdEventRecorded = true;

                return true;
            }

            return false;
        });

        $this->assertTrue($thresholdEventRecorded, 'TransferThresholdReached event should be recorded');
    }

    #[Test]
    public function test_resets_count_after_threshold(): void
    {
        $aggregate = new TransferAggregate();

        // Set count just below threshold
        $aggregate->count = TransferAggregate::COUNT_THRESHOLD - 1;

        $money = new Money(100);

        // First set the currentHash to a known value
        $reflection = new ReflectionClass($aggregate);
        $currentHashProperty = $reflection->getProperty('currentHash');
        $currentHashProperty->setAccessible(true);
        $currentHashProperty->setValue($aggregate, '');

        // Then generate hash using the same state
        $method = $reflection->getMethod('generateHash');
        $method->setAccessible(true);
        $hash = $method->invoke($aggregate, $money);

        $event = new MoneyTransferred(
            from: new AccountUuid('from'),
            to: new AccountUuid('to'),
            money: $money,
            hash: $hash
        );

        $aggregate->applyMoneyTransferred($event);

        // Count should be reset to 0 after hitting threshold
        $this->assertEquals(0, $aggregate->count);
    }

    #[Test]
    public function test_validates_hash_for_duplicate_prevention(): void
    {
        $aggregate = new TransferAggregate();
        $money = new Money(1000);

        // Use reflection to access protected methods
        $reflection = new ReflectionClass($aggregate);

        // First set the currentHash to a known value
        $currentHashProperty = $reflection->getProperty('currentHash');
        $currentHashProperty->setAccessible(true);
        $currentHashProperty->setValue($aggregate, '');

        $generateMethod = $reflection->getMethod('generateHash');
        $generateMethod->setAccessible(true);
        $hash = $generateMethod->invoke($aggregate, $money);

        $storeMethod = $reflection->getMethod('storeHash');
        $storeMethod->setAccessible(true);
        $storeMethod->invoke($aggregate, $hash);

        // Try to use same hash again
        $this->expectException(Exception::class);

        $validateMethod = $reflection->getMethod('validateHash');
        $validateMethod->setAccessible(true);
        $validateMethod->invoke($aggregate, $hash, $money);
    }

    #[Test]
    public function test_handles_different_currency_transfers(): void
    {
        $aggregate = TransferAggregate::fake();

        $from = new AccountUuid('multi-currency-from');
        $to = new AccountUuid('multi-currency-to');

        // Transfer USD
        $aggregate->transfer($from, $to, new Money(1000));

        // Transfer EUR
        $aggregate->transfer($from, $to, new Money(2000));

        // Transfer BTC
        $aggregate->transfer($from, $to, new Money(10000000)); // 0.1 BTC

        // All transfers should be recorded
        $eventCount = 0;
        $aggregate->assertRecorded(function ($event) use (&$eventCount) {
            if ($event instanceof MoneyTransferred) {
                $eventCount++;
            }

            return true;
        });

        $this->assertEquals(3, $eventCount, 'Expected 3 MoneyTransferred events to be recorded');
    }

    #[Test]
    public function test_maintains_count_across_multiple_transfers(): void
    {
        $aggregate = new TransferAggregate();

        $from = new AccountUuid('counter-from');
        $to = new AccountUuid('counter-to');

        // Use reflection to access protected methods
        $reflection = new ReflectionClass($aggregate);
        $currentHashProperty = $reflection->getProperty('currentHash');
        $currentHashProperty->setAccessible(true);
        $generateMethod = $reflection->getMethod('generateHash');
        $generateMethod->setAccessible(true);

        // Apply multiple transfer events
        for ($i = 1; $i <= 5; $i++) {
            $money = new Money($i * 100);

            // Set currentHash to empty before generating
            $currentHashProperty->setValue($aggregate, '');

            // Generate hash for this transfer
            $hash = $generateMethod->invoke($aggregate, $money);

            $event = new MoneyTransferred(
                from: $from,
                to: $to,
                money: $money,
                hash: $hash
            );
            $aggregate->applyMoneyTransferred($event);
        }

        $this->assertEquals(5, $aggregate->count);
    }

    #[Test]
    public function test_handles_large_transfer_amounts(): void
    {
        $aggregate = TransferAggregate::fake();

        $from = new AccountUuid('large-from');
        $to = new AccountUuid('large-to');
        $money = new Money(100000000); // $1,000,000.00

        $aggregate->transfer($from, $to, $money);

        $eventRecorded = false;
        $aggregate->assertRecorded(function ($event) use (&$eventRecorded) {
            if ($event instanceof MoneyTransferred && $event->money->getAmount() === 100000000) {
                $eventRecorded = true;

                return true;
            }

            return false;
        });

        $this->assertTrue($eventRecorded, 'Large transfer event should be recorded');
    }

    #[Test]
    public function test_snapshot_preserves_transfer_count(): void
    {
        // Create a real aggregate
        $uuid = (string) Str::uuid();
        $aggregate = TransferAggregate::retrieve($uuid);

        // Make some transfers to build up count
        $from = new AccountUuid('snapshot-from');
        $to = new AccountUuid('snapshot-to');

        for ($i = 0; $i < 5; $i++) {
            $aggregate->transfer($from, $to, new Money(100));
        }

        // Persist the aggregate
        $aggregate->persist();

        // Create a snapshot
        $aggregate->snapshot();

        // Retrieve the aggregate again (will use snapshot)
        $newAggregate = TransferAggregate::retrieve($uuid);

        // Verify the count was preserved through snapshot
        $this->assertEquals(5, $newAggregate->count);
    }

    #[Test]
    public function test_transfer_between_same_account_allowed(): void
    {
        $aggregate = TransferAggregate::fake();

        $account = new AccountUuid('self-transfer-account');
        $money = new Money(500);

        // Transfer from account to itself (edge case)
        $aggregate->transfer($account, $account, $money);

        $eventRecorded = false;
        $aggregate->assertRecorded(function ($event) use ($account, &$eventRecorded) {
            if (
                $event instanceof MoneyTransferred &&
                (string) $event->from === (string) $account &&
                (string) $event->to === (string) $account
            ) {
                $eventRecorded = true;

                return true;
            }

            return false;
        });

        $this->assertTrue($eventRecorded, 'Self-transfer event should be recorded');
    }
}
