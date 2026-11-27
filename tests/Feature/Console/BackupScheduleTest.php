<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\Backup\Tasks\Backup\FileSelection;
use Spatie\DbDumper\Databases\MySql;

/**
 * The RPO claim is only as real as the scheduled job behind it. These tests
 * pin the spatie/laravel-backup wiring: the nightly backup and the retention
 * cleanup must stay registered and production-gated. The backup holds the
 * database (the event store is the ledger) and the files the app writes that
 * exist nowhere else: regulatory reports, reconciliation reports, contracts
 * and user uploads on the public disk.
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

it('registers backup:run with files daily at 01:30 UTC, production-only', function (): void {
    $event = backupScheduleEvent('backup:run');

    expect($event->command)->not->toContain('--only-db');
    expect($event->command)->not->toContain('--only-files');
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

it('backs up the database and the stored report, contract and upload folders', function (): void {
    expect(config('backup.backup.source.files.include'))->toBe([
        storage_path('app/regulatory'),
        storage_path('app/reconciliation'),
        storage_path('app/contracts'),
        storage_path('app/public'),
    ]);

    $databases = config('backup.backup.source.databases');
    expect($databases)->toBeArray()->not->toBeEmpty();
});

it('selects the stored files and leaves out temporary files and old local backups', function (): void {
    $marker = 'backup-selection-' . bin2hex(random_bytes(4));
    $keep = [
        storage_path("app/regulatory/{$marker}.json"),
        storage_path("app/reconciliation/{$marker}.json"),
        storage_path("app/contracts/{$marker}.pdf"),
        storage_path("app/public/avatars/{$marker}.png"),
    ];
    $skip = [
        storage_path("app/Zelta/{$marker}.zip"),
        storage_path("app/backup-temp/{$marker}.sql"),
        storage_path("app/kyc-temp/{$marker}.jpg"),
        storage_path("app/gdpr-exports/{$marker}.json.enc"),
        storage_path("app/livewire-tmp/{$marker}.tmp"),
        storage_path("framework/cache/{$marker}"),
    ];

    $made = [];
    foreach ([...$keep, ...$skip] as $file) {
        $dir = dirname($file);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
            $made[] = $dir;
        }
        file_put_contents($file, 'x');
    }

    try {
        $files = config('backup.backup.source.files');
        $selected = collect(iterator_to_array(
            FileSelection::create($files['include'])->excludeFilesFrom($files['exclude'])->selectedFiles(),
            false,
        ))->filter(fn (string $path): bool => str_contains($path, $marker))->sort()->values()->all();

        expect($selected)->toBe(collect($keep)->sort()->values()->all());
    } finally {
        foreach ([...$keep, ...$skip] as $file) {
            @unlink($file);
        }
        foreach (array_reverse($made) as $dir) {
            @rmdir($dir);
        }
    }
});

it('stores the files in the zip under paths relative to the app root', function (): void {
    expect(config('backup.backup.source.files.relative_path'))->toBe(base_path());
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

/*
 * The nightly dump keeps every table's structure but skips the rows of the
 * Pulse and Telescope monitoring tables. Those rows are short-lived request
 * metrics, and they made up most of the uncompressed dump. The dump also runs
 * in one consistent read so it never locks the live tables.
 */
dataset('backupConnections', ['mysql', 'mariadb']);

it('dumps in one consistent read and skips only the monitoring table rows', function (string $connection): void {
    config(["database.connections.{$connection}.database" => 'zelta']);

    $dumper = DbDumperFactory::createFromConnection($connection);
    expect($dumper)->toBeInstanceOf(MySql::class);
    assert($dumper instanceof MySql);

    $command = $dumper->getDumpCommand('dump.sql', 'credentials.cnf');

    expect($command)->toContain('--single-transaction');
    expect($command)->not->toContain('--ignore-table=');

    preg_match_all('/--ignore-table-data=zelta\.(\w+)/', $command, $matches);
    expect($matches[1])->toEqualCanonicalizing([
        'pulse_aggregates',
        'pulse_entries',
        'pulse_values',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ]);
})->with('backupConnections');
