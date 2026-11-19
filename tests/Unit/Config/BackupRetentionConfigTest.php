<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function loadBackupConfig(): array
{
    return require dirname(__DIR__, 3) . '/config/backup.php';
}

afterEach(function (): void {
    foreach (['BACKUP_KEEP_ALL_DAYS', 'BACKUP_KEEP_DAILY_DAYS', 'BACKUP_KEEP_WEEKLY_WEEKS', 'BACKUP_KEEP_MONTHLY_MONTHS', 'BACKUP_KEEP_YEARLY_YEARS', 'BACKUP_MAX_MEGABYTES'] as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
});

it('keeps the package retention defaults when nothing is set', function (): void {
    $strategy = loadBackupConfig()['cleanup']['default_strategy'];

    expect($strategy['keep_all_backups_for_days'])->toBe(7)
        ->and($strategy['keep_daily_backups_for_days'])->toBe(16)
        ->and($strategy['keep_weekly_backups_for_weeks'])->toBe(8)
        ->and($strategy['keep_monthly_backups_for_months'])->toBe(4)
        ->and($strategy['keep_yearly_backups_for_years'])->toBe(2)
        ->and($strategy['delete_oldest_backups_when_using_more_megabytes_than'])->toBe(5000);
});

it('reads every retention period and the size cap from the environment', function (): void {
    $values = ['BACKUP_KEEP_ALL_DAYS' => '2', 'BACKUP_KEEP_DAILY_DAYS' => '0', 'BACKUP_KEEP_WEEKLY_WEEKS' => '0', 'BACKUP_KEEP_MONTHLY_MONTHS' => '0', 'BACKUP_KEEP_YEARLY_YEARS' => '0', 'BACKUP_MAX_MEGABYTES' => '1500'];
    foreach ($values as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    $strategy = loadBackupConfig()['cleanup']['default_strategy'];

    expect($strategy['keep_all_backups_for_days'])->toBe(2)
        ->and($strategy['keep_daily_backups_for_days'])->toBe(0)
        ->and($strategy['keep_weekly_backups_for_weeks'])->toBe(0)
        ->and($strategy['keep_monthly_backups_for_months'])->toBe(0)
        ->and($strategy['keep_yearly_backups_for_years'])->toBe(0)
        ->and($strategy['delete_oldest_backups_when_using_more_megabytes_than'])->toBe(1500);
});
