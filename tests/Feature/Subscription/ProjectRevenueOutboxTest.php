<?php

declare(strict_types=1);

use App\Domain\Subscription\Jobs\ProjectRevenueOutbox;
use App\Domain\Subscription\Models\RevenueEvent;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

it('projects a pending subscription_initial outbox row into revenue_events and marks delivered', function () {
    $row = RevenueOutboxEvent::query()->create([
        'source_type'     => RevenueOutboxEvent::SOURCE_STRIPE,
        'source_event_id' => 'evt_proj_001',
        'event_kind'      => 'customer.subscription.created',
        'payload'         => [
            'userId'       => 1,
            'aggregateId'  => 'sub_proj_001',
            'amount'       => 499,
            'decimals'     => 2,
            'denomination' => 'EUR',
            'emittedAt'    => now()->toIso8601String(),
        ],
        'status'   => RevenueOutboxEvent::STATUS_PENDING,
        'attempts' => 0,
    ]);

    (new ProjectRevenueOutbox($row->id))->handle();

    $row->refresh();
    expect($row->status)->toBe(RevenueOutboxEvent::STATUS_DELIVERED);
    expect($row->delivered_at)->not()->toBeNull();

    $event = RevenueEvent::query()->where('source_event_id', 'evt_proj_001')->first();
    expect($event)->not()->toBeNull();
    expect($event->event_type)->toBe(RevenueEvent::TYPE_SUBSCRIPTION_INITIAL);
    expect($event->amount)->toBe(499);
    expect($event->amount_decimals)->toBe(2);
    expect($event->amount_denomination)->toBe('EUR');
    expect($event->aggregate_id)->toBe('sub_proj_001');
});

it('maps invoice.payment_succeeded to subscription_renewal', function () {
    $row = RevenueOutboxEvent::query()->create([
        'source_type'     => RevenueOutboxEvent::SOURCE_STRIPE,
        'source_event_id' => 'evt_renewal_proj',
        'event_kind'      => 'invoice.payment_succeeded',
        'payload'         => [
            'userId'       => 1,
            'aggregateId'  => 'sub_renew',
            'amount'       => 499,
            'decimals'     => 2,
            'denomination' => 'EUR',
        ],
        'status'   => RevenueOutboxEvent::STATUS_PENDING,
        'attempts' => 0,
    ]);

    (new ProjectRevenueOutbox($row->id))->handle();

    $event = RevenueEvent::query()->where('source_event_id', 'evt_renewal_proj')->first();
    expect($event)->not()->toBeNull();
    expect($event->event_type)->toBe(RevenueEvent::TYPE_SUBSCRIPTION_RENEWAL);
});

it('maps charge.refunded to refund event-type with negative amount', function () {
    $row = RevenueOutboxEvent::query()->create([
        'source_type'     => RevenueOutboxEvent::SOURCE_STRIPE,
        'source_event_id' => 'evt_refund_proj',
        'event_kind'      => 'charge.refunded',
        'payload'         => [
            'userId'       => 1,
            'aggregateId'  => 'ch_refund',
            'amount'       => -499,
            'decimals'     => 2,
            'denomination' => 'EUR',
        ],
        'status'   => RevenueOutboxEvent::STATUS_PENDING,
        'attempts' => 0,
    ]);

    (new ProjectRevenueOutbox($row->id))->handle();

    $event = RevenueEvent::query()->where('source_event_id', 'evt_refund_proj')->first();
    expect($event)->not()->toBeNull();
    expect($event->event_type)->toBe(RevenueEvent::TYPE_REFUND);
    expect($event->amount)->toBe(-499);
});

it('is idempotent — re-running on a delivered row is a no-op', function () {
    $row = RevenueOutboxEvent::query()->create([
        'source_type'     => RevenueOutboxEvent::SOURCE_STRIPE,
        'source_event_id' => 'evt_idem_proj',
        'event_kind'      => 'customer.subscription.created',
        'payload'         => [
            'userId'       => 1,
            'aggregateId'  => 'sub_idem',
            'amount'       => 499,
            'decimals'     => 2,
            'denomination' => 'EUR',
        ],
        'status'   => RevenueOutboxEvent::STATUS_PENDING,
        'attempts' => 0,
    ]);

    (new ProjectRevenueOutbox($row->id))->handle();
    (new ProjectRevenueOutbox($row->id))->handle();

    expect(RevenueEvent::query()->where('source_event_id', 'evt_idem_proj')->count())->toBe(1);
});

it('sweeps all pending rows when called with rowId=null', function () {
    foreach (range(1, 3) as $i) {
        RevenueOutboxEvent::query()->create([
            'source_type'     => RevenueOutboxEvent::SOURCE_STRIPE,
            'source_event_id' => "evt_sweep_{$i}",
            'event_kind'      => 'invoice.payment_succeeded',
            'payload'         => [
                'amount'       => 499,
                'decimals'     => 2,
                'denomination' => 'EUR',
                'aggregateId'  => "sub_sweep_{$i}",
            ],
            'status'   => RevenueOutboxEvent::STATUS_PENDING,
            'attempts' => 0,
        ]);
    }

    (new ProjectRevenueOutbox(null))->handle();

    expect(RevenueEvent::query()->count())->toBe(3);
    expect(RevenueOutboxEvent::query()->where('status', 'delivered')->count())->toBe(3);
});
