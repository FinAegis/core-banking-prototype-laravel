<?php

declare(strict_types=1);

use App\Domain\Exchange\Jobs\CheckArbitrageOpportunitiesJob;
use App\Providers\ExternalExchangeServiceProvider;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Boot the provider with arbitrage on and return the arbitrage schedule entry.
 *
 * The app is already booted in tests, so the provider's booted() callback runs
 * straight away, exactly as it would during a real start with the flag on.
 */
function bootProviderWithArbitrage(int $checkIntervalSeconds): ?Event
{
    config([
        'trading.arbitrage.enabled'        => true,
        'trading.arbitrage.check_interval' => $checkIntervalSeconds,
    ]);

    app()->instance(Schedule::class, new Schedule());

    (new ExternalExchangeServiceProvider(app()))->boot();

    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => $event->description === CheckArbitrageOpportunitiesJob::class);
}

it('boots with arbitrage enabled and schedules the check every minute by default', function () {
    $event = bootProviderWithArbitrage(60);

    expect($event)->not->toBeNull()
        ->and($event?->expression)->toBe('* * * * *')
        ->and($event?->withoutOverlapping)->toBeTrue()
        ->and($event?->onOneServer)->toBeTrue();
});

it('turns the check interval from seconds into whole minutes', function () {
    expect(bootProviderWithArbitrage(300)?->expression)->toBe('*/5 * * * *');
});

it('never schedules the check more often than once a minute', function () {
    expect(bootProviderWithArbitrage(30)?->expression)->toBe('* * * * *');
});

it('does not schedule the check when arbitrage is disabled', function () {
    config(['trading.arbitrage.enabled' => false]);
    app()->instance(Schedule::class, new Schedule());

    (new ExternalExchangeServiceProvider(app()))->boot();

    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => $event->description === CheckArbitrageOpportunitiesJob::class);

    expect($event)->toBeNull();
});
