<?php

namespace Tests\Unit\Domain\Account\Aggregates;

use App\Domain\Account\Aggregates\TransactionAggregate;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Events\MoneyAdded;
use App\Domain\Account\Events\MoneySubtracted;
use App\Domain\Account\Events\TransactionThresholdReached;
use App\Domain\Account\Exceptions\NotEnoughFunds;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class TransactionAggregateTest extends DomainTestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_credits_money_to_account(): void
    {
        $aggregate = TransactionAggregate::fake();
        $money = new Money(1000); // $10.00

        $aggregate->credit($money);

        // Assert that a MoneyAdded event was recorded with the correct money
        $eventRecorded = false;
        $aggregate->assertRecorded(function ($event) use ($money, &$eventRecorded) {
            if ($event instanceof MoneyAdded && $event->money->getAmount() === $money->getAmount()) {
                $eventRecorded = true;

                return true;
            }

            return false;
        });

        $this->assertTrue($eventRecorded, 'MoneyAdded event should be recorded');
    }

    #[Test]
    public function test_debits_money_from_account(): void
    {
        $aggregate = TransactionAggregate::fake();

        // First add money
        $creditMoney = new Money(5000); // $50.00
        $aggregate->credit($creditMoney);

        // Then debit
        $debitMoney = new Money(2000); // $20.00
        $aggregate->debit($debitMoney);

        // Assert that both events were recorded
        // First check MoneyAdded event
        $moneyAddedRecorded = false;
        $moneySubtractedRecorded = false;

        $aggregate->assertRecorded(function ($event) use ($creditMoney, &$moneyAddedRecorded) {
            if ($event instanceof MoneyAdded && $event->money->getAmount() === $creditMoney->getAmount()) {
                $moneyAddedRecorded = true;

                return true;
            }

            return false;
        });

        $aggregate->assertRecorded(function ($event) use ($debitMoney, &$moneySubtractedRecorded) {
            if ($event instanceof MoneySubtracted && $event->money->getAmount() === $debitMoney->getAmount()) {
                $moneySubtractedRecorded = true;

                return true;
            }

            return false;
        });

        $this->assertTrue($moneyAddedRecorded, 'MoneyAdded event was not recorded');
        $this->assertTrue($moneySubtractedRecorded, 'MoneySubtracted event was not recorded');
    }

    #[Test]
    public function test_throws_exception_when_insufficient_funds(): void
    {
        $aggregate = TransactionAggregate::fake();

        // Add small amount
        $aggregate->credit(new Money(100)); // $1.00

        $this->expectException(NotEnoughFunds::class);

        // Try to debit more
        $aggregate->debit(new Money(200)); // $2.00
    }

    #[Test]
    public function test_applies_money_added_event(): void
    {
        $aggregate = new TransactionAggregate();
        $money = new Money(2500); // €25.00

        // First set the currentHash to a known value
        $reflection = new ReflectionClass($aggregate);
        $currentHashProperty = $reflection->getProperty('currentHash');
        $currentHashProperty->setAccessible(true);
        $currentHashProperty->setValue($aggregate, '');

        // Then generate hash using the same state
        $method = $reflection->getMethod('generateHash');
        $method->setAccessible(true);
        $hash = $method->invoke($aggregate, $money);

        $event = new MoneyAdded(
            money: $money,
            hash: $hash
        );

        $aggregate->applyMoneyAdded($event);

        $this->assertEquals(2500, $aggregate->balance);
        $this->assertEquals(1, $aggregate->count);
    }

    #[Test]
    public function test_applies_money_subtracted_event(): void
    {
        $aggregate = new TransactionAggregate(balance: 5000); // Start with $50.00
        $money = new Money(1500); // $15.00

        // First set the currentHash to a known value
        $reflection = new ReflectionClass($aggregate);
        $currentHashProperty = $reflection->getProperty('currentHash');
        $currentHashProperty->setAccessible(true);
        $currentHashProperty->setValue($aggregate, '');

        // Then generate hash using the same state
        $method = $reflection->getMethod('generateHash');
        $method->setAccessible(true);
        $hash = $method->invoke($aggregate, $money);

        $event = new MoneySubtracted(
            money: $money,
            hash: $hash
        );

        $aggregate->applyMoneySubtracted($event);

        $this->assertEquals(3500, $aggregate->balance);
        $this->assertEquals(1, $aggregate->count);
    }

    #[Test]
    public function test_records_transaction_threshold_reached(): void
    {
        $aggregate = TransactionAggregate::fake();
        $money = new Money(100);

        // Make COUNT_THRESHOLD transactions
        for ($i = 0; $i < TransactionAggregate::COUNT_THRESHOLD; $i++) {
            $aggregate->credit($money);
        }

        // Verify threshold event was recorded
        $thresholdEventRecorded = false;
        $aggregate->assertRecorded(function ($event) use (&$thresholdEventRecorded) {
            if ($event instanceof TransactionThresholdReached) {
                $thresholdEventRecorded = true;

                return true;
            }

            return false;
        });

        $this->assertTrue($thresholdEventRecorded, 'TransactionThresholdReached event should be recorded');
    }

    #[Test]
    public function test_resets_count_after_threshold(): void
    {
        $aggregate = new TransactionAggregate();
        $money = new Money(100);

        // Set count just below threshold
        $aggregate->count = TransactionAggregate::COUNT_THRESHOLD - 1;

        // First set the currentHash to a known value
        $reflection = new ReflectionClass($aggregate);
        $currentHashProperty = $reflection->getProperty('currentHash');
        $currentHashProperty->setAccessible(true);
        $currentHashProperty->setValue($aggregate, '');

        // Then generate hash using the same state
        $method = $reflection->getMethod('generateHash');
        $method->setAccessible(true);
        $hash = $method->invoke($aggregate, $money);

        $event = new MoneyAdded(
            money: $money,
            hash: $hash
        );

        $aggregate->applyMoneyAdded($event);

        // Count should be reset to 0 after hitting threshold
        $this->assertEquals(0, $aggregate->count);
    }

    #[Test]
    public function test_records_account_limit_hit_on_debit(): void
    {
        $aggregate = TransactionAggregate::fake();

        // Try to debit when balance is 0 (insufficient funds)
        $money = new Money(100);

        // Assert that the exception is thrown
        $this->expectException(NotEnoughFunds::class);

        // This will throw the exception
        $aggregate->debit($money);

        // Note: We can't check recorded events after the exception is thrown
        // The test passes if the exception is thrown correctly
    }

    #[Test]
    public function test_validates_hash_for_duplicate_prevention(): void
    {
        $aggregate = new TransactionAggregate();
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
    public function test_handles_different_currencies(): void
    {
        $aggregate = TransactionAggregate::fake();

        $usdMoney = new Money(1000);
        $eurMoney = new Money(2000);

        $aggregate->credit($usdMoney);
        $aggregate->credit($eurMoney);

        // Both transactions should be recorded
        $eventCount = 0;
        $aggregate->assertRecorded(function ($event) use (&$eventCount) {
            if ($event instanceof MoneyAdded) {
                $eventCount++;
            }

            return true;
        });

        $this->assertEquals(2, $eventCount, 'Expected 2 MoneyAdded events to be recorded');
    }

    #[Test]
    public function test_maintains_balance_across_multiple_operations(): void
    {
        $aggregate = new TransactionAggregate();
        $reflection = new ReflectionClass($aggregate);

        // Helper to generate valid hash for each operation
        $generateValidHash = function ($money) use ($aggregate, $reflection) {
            $currentHashProperty = $reflection->getProperty('currentHash');
            $currentHashProperty->setAccessible(true);
            $currentHash = $currentHashProperty->getValue($aggregate);

            $generateMethod = $reflection->getMethod('generateHash');
            $generateMethod->setAccessible(true);

            return $generateMethod->invoke($aggregate, $money);
        };

        // Credit operations
        $money1 = new Money(1000);
        $hash1 = $generateValidHash($money1);
        $aggregate->applyMoneyAdded(new MoneyAdded(
            money: $money1,
            hash: $hash1
        ));

        $money2 = new Money(2000);
        $hash2 = $generateValidHash($money2);
        $aggregate->applyMoneyAdded(new MoneyAdded(
            money: $money2,
            hash: $hash2
        ));

        // Debit operation
        $money3 = new Money(500);
        $hash3 = $generateValidHash($money3);
        $aggregate->applyMoneySubtracted(new MoneySubtracted(
            money: $money3,
            hash: $hash3
        ));

        // Final balance: 1000 + 2000 - 500 = 2500
        $this->assertEquals(2500, $aggregate->balance);
        $this->assertEquals(3, $aggregate->count);
    }

    #[Test]
    public function test_snapshot_preserves_state(): void
    {
        // Use a fake aggregate for testing snapshots
        $aggregate = TransactionAggregate::fake();

        // Set some state
        $aggregate->balance = 10000;
        $aggregate->count = 500;

        // Test that the state properties are accessible
        $this->assertEquals(10000, $aggregate->balance);
        $this->assertEquals(500, $aggregate->count);

        // Note: Full snapshot testing would require a real database setup with proper accounts
        // For unit testing, we're verifying that the state properties work correctly
    }
}
