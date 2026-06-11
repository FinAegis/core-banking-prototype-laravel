<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * The 15-minute RPO claim is only as real as the scheduled job behind it.
 * These tests pin the spatie/laravel-backup wiring: the nightly DB-only
 * backup + retention cleanup must stay registered, production-gated, and
 * DB-only (the event store is the ledger; files are deploy artifacts).
 */
function backupScheduleEvent(string $needle): Event
{
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $match = collect($schedule->events())
        ->first(fn ($e) => str_contains($e->command ?? '', $needle));

    if (! $match instanceof Event) {
        throw new RuntimeException("Expected a scheduled event for {$needle}");
    }

    return $match;
}

it('registers backup:run --only-db daily at 01:30 UTC, production-only', function (): void {
    $event = backupScheduleEvent('backup:run');

    expect($event->command)->toContain('--only-db');
    expect($event->expression)->toBe('30 1 * * *');
    expect($event->timezone)->toBe('UTC');
    expect($event->environments)->toBe(['production']);
});

it('registers backup:clean daily at 02:30 UTC, production-only', function (): void {
    $event = backupScheduleEvent('backup:clean');

    expect($event->expression)->toBe('30 2 * * *');
    expect($event->timezone)->toBe('UTC');
    expect($event->environments)->toBe(['production']);
});

it('configures DB-only backups (no file sources)', function (): void {
    expect(config('backup.backup.source.files.include'))->toBe([]);

    $databases = config('backup.backup.source.databases');
    expect($databases)->toBeArray()->not->toBeEmpty();
});

it('routes the backup destination through BACKUP_DISK with a local default', function (): void {
    // env() is resolved at config load; in the test env no BACKUP_DISK is set,
    // so the default must be the local disk.
    expect(config('backup.backup.destination.disks'))->toBe(['local']);
    expect(config('backup.monitor_backups.0.disks'))->toBe(['local']);
});

it('keeps the package mail/slack notification channels off by default', function (): void {
    $notifications = config('backup.notifications.notifications');

    expect($notifications)->toBeArray()->not->toBeEmpty();

    foreach ($notifications as $class => $channels) {
        expect($channels)->toBe([], "Backup notification {$class} should not use mail/slack — alerting goes via Log::critical → LOG stack (Slack).");
    }
});
