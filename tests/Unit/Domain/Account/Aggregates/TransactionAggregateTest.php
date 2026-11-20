<?php

declare(strict_types=1);

use App\Domain\Account\Aggregates\TransactionAggregate;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Events\MoneyAdded;
use App\Domain\Account\Events\MoneySubtracted;
use App\Domain\Account\Events\TransactionThresholdReached;
use App\Domain\Account\Exceptions\NotEnoughFunds;

uses(Tests\TestCase::class);

describe('TransactionAggregate', function () {
    describe('credit', function () {
        it('records a MoneyAdded event with the correct amount', function () {
            $aggregate = TransactionAggregate::fake();
            $money = new Money(1000); // $10.00

            $aggregate->credit($money);

            $eventRecorded = false;
            $aggregate->assertRecorded(function ($event) use ($money, &$eventRecorded) {
                if ($event instanceof MoneyAdded && $event->money->getAmount() === $money->getAmount()) {
                    $eventRecorded = true;

                    return true;
                }

                return false;
            });

            expect($eventRecorded)->toBeTrue();
        });

        it('records multiple MoneyAdded events for sequential credits', function () {
            $aggregate = TransactionAggregate::fake();

            $aggregate->credit(new Money(1000));
            $aggregate->credit(new Money(2000));

            $eventCount = 0;
            $aggregate->assertRecorded(function ($event) use (&$eventCount) {
                if ($event instanceof MoneyAdded) {
                    $eventCount++;
                }

                return true;
            });

            expect($eventCount)->toBe(2);
        });
    });

    describe('debit', function () {
        it('records both MoneyAdded and MoneySubtracted events', function () {
            $aggregate = TransactionAggregate::fake();

            $creditMoney = new Money(5000); // $50.00
            $aggregate->credit($creditMoney);

            $debitMoney = new Money(2000); // $20.00
            $aggregate->debit($debitMoney);

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

            expect($moneyAddedRecorded)->toBeTrue();
            expect($moneySubtractedRecorded)->toBeTrue();
        });

        it('throws NotEnoughFunds when balance is insufficient', function () {
            $aggregate = TransactionAggregate::fake();

            $aggregate->credit(new Money(100)); // $1.00

            expect(fn () => $aggregate->debit(new Money(200))) // $2.00
                ->toThrow(NotEnoughFunds::class);
        });

        it('throws NotEnoughFunds when debiting from zero balance', function () {
            $aggregate = TransactionAggregate::fake();

            expect(fn () => $aggregate->debit(new Money(100)))
                ->toThrow(NotEnoughFunds::class);
        });
    });

    describe('applyMoneyAdded', function () {
        it('increases balance by the event amount', function () {
            $aggregate = TransactionAggregate::fake();
            $aggregate->credit(new Money(2500));

            // After applying event, balance should match
            expect($aggregate->aggregateRoot()->balance)->toBe(2500);
        });

        it('increments the transaction count', function () {
            $aggregate = TransactionAggregate::fake();
            $aggregate->credit(new Money(100));

            expect($aggregate->aggregateRoot()->count)->toBe(1);
        });
    });

    describe('applyMoneySubtracted', function () {
        it('decreases balance by the event amount', function () {
            $aggregate = TransactionAggregate::fake();
            $aggregate->credit(new Money(5000));
            $aggregate->debit(new Money(1500));

            expect($aggregate->aggregateRoot()->balance)->toBe(3500);
        });
    });

    describe('threshold behavior', function () {
        it('records TransactionThresholdReached at COUNT_THRESHOLD', function () {
            $aggregate = TransactionAggregate::fake();
            $money = new Money(100);

            for ($i = 0; $i < TransactionAggregate::COUNT_THRESHOLD; $i++) {
                $aggregate->credit($money);
            }

            $thresholdEventRecorded = false;
            $aggregate->assertRecorded(function ($event) use (&$thresholdEventRecorded) {
                if ($event instanceof TransactionThresholdReached) {
                    $thresholdEventRecorded = true;

                    return true;
                }

                return false;
            });

            expect($thresholdEventRecorded)->toBeTrue();
        });

        it('resets count to zero after threshold', function () {
            $aggregate = TransactionAggregate::fake();
            $money = new Money(100);

            for ($i = 0; $i < TransactionAggregate::COUNT_THRESHOLD; $i++) {
                $aggregate->credit($money);
            }

            expect($aggregate->aggregateRoot()->count)->toBe(0);
        });
    });

    describe('balance tracking', function () {
        it('maintains correct balance across multiple operations', function () {
            $aggregate = TransactionAggregate::fake();

            $aggregate->credit(new Money(1000));  // +10.00
            $aggregate->credit(new Money(2000));  // +20.00
            $aggregate->debit(new Money(500));    // -5.00

            // Final balance: 1000 + 2000 - 500 = 2500
            expect($aggregate->aggregateRoot()->balance)->toBe(2500);
        });

        it('handles different transaction amounts correctly', function () {
            $aggregate = TransactionAggregate::fake();

            $aggregate->credit(new Money(1000));
            $aggregate->credit(new Money(2000));

            expect($aggregate->aggregateRoot()->balance)->toBe(3000);
            expect($aggregate->aggregateRoot()->count)->toBe(2);
        });
    });

    describe('snapshot', function () {
        it('preserves state properties through the aggregate root', function () {
            $aggregate = TransactionAggregate::fake();

            $aggregate->aggregateRoot()->balance = 10000;
            $aggregate->aggregateRoot()->count = 500;

            expect($aggregate->aggregateRoot()->balance)->toBe(10000);
            expect($aggregate->aggregateRoot()->count)->toBe(500);
        });
    });

    describe('hash validation', function () {
        it('prevents duplicate transaction hashes', function () {
            $aggregate = new TransactionAggregate();
            $money = new Money(1000);

            $reflection = new ReflectionClass($aggregate);

            $currentHashProperty = $reflection->getProperty('currentHash');
            $currentHashProperty->setValue($aggregate, '');

            $generateMethod = $reflection->getMethod('generateHash');
            $hash = $generateMethod->invoke($aggregate, $money);

            $storeMethod = $reflection->getMethod('storeHash');
            $storeMethod->invoke($aggregate, $hash);

            expect(fn () => $reflection->getMethod('validateHash')
                ->invoke($aggregate, $hash, $money))
                ->toThrow(Exception::class);
        });
    });
});
