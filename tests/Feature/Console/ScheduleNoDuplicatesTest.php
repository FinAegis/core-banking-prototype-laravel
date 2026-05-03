<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

it('registers each scheduled command only once', function (): void {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $commands = collect($schedule->events())
        ->map(fn ($event) => $event->command ?? $event->description ?? null)
        ->filter()
        ->values();

    $duplicates = $commands
        ->countBy()
        ->filter(fn (int $count) => $count > 1);

    expect($duplicates->all())->toBe(
        [],
        'Scheduled commands registered more than once: ' . json_encode($duplicates->all())
    );
});
