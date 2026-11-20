<?php

declare(strict_types=1);

use App\Domain\Account\Aggregates\TransferAggregate;
use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Events\MoneyTransferred;
use App\Domain\Account\Events\TransferThresholdReached;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

describe('TransferAggregate', function () {
    describe('transfer', function () {
        it('records a MoneyTransferred event', function () {
            $aggregate = TransferAggregate::fake();

            $from = new AccountUuid('from-account-uuid');
            $to = new AccountUuid('to-account-uuid');
            $money = new Money(2500); // $25.00

            $aggregate->transfer($from, $to, $money);

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

            expect($eventRecorded)->toBeTrue();
        });

        it('records multiple transfers correctly', function () {
            $aggregate = TransferAggregate::fake();

            $from = new AccountUuid('from-uuid');
            $to = new AccountUuid('to-uuid');

            $aggregate->transfer($from, $to, new Money(1000));
            $aggregate->transfer($from, $to, new Money(2000));
            $aggregate->transfer($from, $to, new Money(10000000));

            $eventCount = 0;
            $aggregate->assertRecorded(function ($event) use (&$eventCount) {
                if ($event instanceof MoneyTransferred) {
                    $eventCount++;
                }

                return true;
            });

            expect($eventCount)->toBe(3);
        });

        it('allows transfer between same account (self-transfer)', function () {
            $aggregate = TransferAggregate::fake();

            $account = new AccountUuid('self-transfer-account');
            $money = new Money(500);

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

            expect($eventRecorded)->toBeTrue();
        });

        it('handles large transfer amounts', function () {
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

            expect($eventRecorded)->toBeTrue();
        });
    });

    describe('applyMoneyTransferred', function () {
        it('increments the transfer count', function () {
            $aggregate = TransferAggregate::fake();

            $from = new AccountUuid('sender-uuid');
            $to = new AccountUuid('receiver-uuid');

            $aggregate->transfer($from, $to, new Money(5000));

            expect($aggregate->aggregateRoot()->count)->toBe(1);
        });

        it('maintains count across multiple transfers', function () {
            $aggregate = TransferAggregate::fake();

            $from = new AccountUuid('counter-from');
            $to = new AccountUuid('counter-to');

            for ($i = 0; $i < 5; $i++) {
                $aggregate->transfer($from, $to, new Money(($i + 1) * 100));
            }

            expect($aggregate->aggregateRoot()->count)->toBe(5);
        });
    });

    describe('threshold behavior', function () {
        it('records TransferThresholdReached at COUNT_THRESHOLD', function () {
            $aggregate = TransferAggregate::fake();

            $from = new AccountUuid('from-uuid');
            $to = new AccountUuid('to-uuid');
            $money = new Money(100);

            for ($i = 0; $i < TransferAggregate::COUNT_THRESHOLD; $i++) {
                $aggregate->transfer($from, $to, $money);
            }

            $thresholdEventRecorded = false;
            $aggregate->assertRecorded(function ($event) use (&$thresholdEventRecorded) {
                if ($event instanceof TransferThresholdReached) {
                    $thresholdEventRecorded = true;

                    return true;
                }

                return false;
            });

            expect($thresholdEventRecorded)->toBeTrue();
        });

        it('resets count to zero after threshold', function () {
            $aggregate = TransferAggregate::fake();

            $from = new AccountUuid('threshold-from');
            $to = new AccountUuid('threshold-to');
            $money = new Money(100);

            for ($i = 0; $i < TransferAggregate::COUNT_THRESHOLD; $i++) {
                $aggregate->transfer($from, $to, $money);
            }

            expect($aggregate->aggregateRoot()->count)->toBe(0);
        });
    });

    describe('snapshot', function () {
        it('preserves transfer count through snapshot cycle', function () {
            $uuid = (string) Str::uuid();
            $aggregate = TransferAggregate::retrieve($uuid);

            $from = new AccountUuid('snapshot-from');
            $to = new AccountUuid('snapshot-to');

            for ($i = 0; $i < 5; $i++) {
                $aggregate->transfer($from, $to, new Money(100));
            }

            $aggregate->persist();
            $aggregate->snapshot();

            $newAggregate = TransferAggregate::retrieve($uuid);

            expect($newAggregate->count)->toBe(5);
        });
    });

    describe('hash validation', function () {
        it('prevents duplicate transfer hashes', function () {
            $aggregate = new TransferAggregate();
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
