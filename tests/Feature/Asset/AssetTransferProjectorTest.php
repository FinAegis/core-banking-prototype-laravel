<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Hash;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Asset\Events\AssetTransferFailed;
use App\Domain\Asset\Projectors\AssetTransferProjector;
use Illuminate\Support\Facades\DB;

/*
 * The projector used to look up a transfer row by a `hash` column that
 * neither `transfers` table has. SQLite reads the unknown quoted name as text
 * and returns nothing; MySQL and MariaDB throw. The failure handler ran that
 * lookup first, so this pins that it is gone. (The completion handler fails
 * earlier, on AccountUuid::toString(), which is a separate known defect.)
 */

/** @return list<string> */
function projectorQueries(Closure $run): array
{
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $run();

    return $queries;
}

/**
 * @param  list<string>  $queries
 * @return list<string>
 */
function transfersByHash(array $queries): array
{
    return array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'transfers') && str_contains($sql, '"hash"')));
}

it('records a failed asset transfer without looking transfers up by hash', function (): void {
    $from = Account::factory()->create();
    $to = Account::factory()->create();

    $event = new AssetTransferFailed(
        AccountUuid::fromString((string) $from->uuid),
        AccountUuid::fromString((string) $to->uuid),
        'USD',
        'EUR',
        new Money(2_500),
        'rate expired',
        Hash::fromData('asset-transfer-failed-test'),
    );

    $queries = projectorQueries(function () use ($event): void {
        try {
            (new AssetTransferProjector())->onAssetTransferFailed($event);
        } catch (Error) {
            // AccountUuid::toString() in the log line: the separate known defect.
        }
    });

    expect(transfersByHash($queries))->toBe([]);
});
