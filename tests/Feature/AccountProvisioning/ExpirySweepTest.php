<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use RuntimeException;

it('registers account:disable-reviewer --all-expired on the daily schedule', function () {
    $schedule = app(Schedule::class);
    $events = collect($schedule->events());

    $match = $events->first(fn ($e) => str_contains($e->command ?? '', 'account:disable-reviewer --all-expired --allow-production'));

    if (! $match instanceof Event) {
        throw new RuntimeException('Expected scheduled event for account:disable-reviewer --all-expired --allow-production');
    }

    expect($match->expression)->toBe('10 0 * * *');
});
