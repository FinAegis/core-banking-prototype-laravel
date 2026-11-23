<?php

declare(strict_types=1);

namespace Tests\MultiConnection\Asset;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Asset\Aggregates\AssetTransferAggregate;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Services\AssetTransferService;
use Illuminate\Support\Str;

// The asset transfer projector debits, credits and writes both history rows in
// one transaction on the balances' (tenant) connection, while the stored events
// go to the default connection. On SQLite both are one database, which hides
// connection mix-ups, missing NOT NULL values (transaction_projections.hash) and
// non-UUID aggregate ids. This runs the same completion against real MySQL with
// separate sessions.
beforeEach(function (): void {
    Asset::factory()->create(['code' => 'USD', 'name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::factory()->create(['code' => 'EUR', 'name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
});

function mcBalance(Account $account, string $asset): int
{
    return (int) AccountBalance::query()->where('account_uuid', $account->uuid)->where('asset_code', $asset)->value('balance');
}

it('books a same-account conversion with balances and history on the tenant connection', function (): void {
    $account = Account::factory()->create(['uuid' => (string) Str::uuid()]);
    AccountBalance::query()->create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 10_000]);
    $transferId = (string) Str::uuid();
    $uuid = new AccountUuid($account->uuid);

    AssetTransferAggregate::retrieve($transferId)
        ->initiate($uuid, $uuid, 'USD', 'EUR', new Money(10_000), new Money(2_900), 0.29, 'Wallet currency conversion')
        ->complete($transferId)
        ->persist();

    expect(mcBalance($account, 'USD'))->toBe(0)
        ->and(mcBalance($account, 'EUR'))->toBe(2_900)
        ->and(TransactionProjection::query()->where('account_uuid', $account->uuid)->where('reference', $transferId)->count())->toBe(2);
});

it('moves both balances for a service transfer under real multi-session topology', function (): void {
    $from = Account::factory()->create(['uuid' => (string) Str::uuid()]);
    $to = Account::factory()->create(['uuid' => (string) Str::uuid()]);
    AccountBalance::query()->create(['account_uuid' => $from->uuid, 'asset_code' => 'USD', 'balance' => 5_000]);

    app(AssetTransferService::class)->transfer((string) $from->uuid, (string) $to->uuid, 'USD', '25.00');

    expect(mcBalance($from, 'USD'))->toBe(2_500)
        ->and(mcBalance($to, 'USD'))->toBe(2_500);
});
