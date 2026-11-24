<?php

declare(strict_types=1);

namespace Tests\MultiConnection\Console;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Aggregates\AssetTransactionAggregate;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Projectors\AssetTransactionProjector;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

// event:replay used to wrap the whole replay in a transaction on the default
// connection, around projectors that write on the tenant connection in their
// own transactions. With real separate sessions the replay must open no
// default-connection transaction and leave applied events alone.
it('replays without a transaction on the default connection', function (): void {
    Asset::factory()->create(['code' => 'USD', 'name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    $account = Account::factory()->create(['uuid' => (string) Str::uuid()]);
    AssetTransactionAggregate::retrieve((string) Str::uuid())
        ->credit(new AccountUuid($account->uuid), 'USD', new Money(1_000))
        ->persist();

    $defaultConnection = (string) config('database.default');
    $defaultTransactions = 0;
    Event::listen(TransactionBeginning::class, function (TransactionBeginning $event) use ($defaultConnection, &$defaultTransactions): void {
        if ($event->connectionName === $defaultConnection) {
            $defaultTransactions++;
        }
    });

    $this->artisan('event:replay', ['--projector' => AssetTransactionProjector::class])
        ->expectsConfirmation('This will replay events through projectors. Continue?', 'yes')
        ->assertSuccessful();

    expect($defaultTransactions)->toBe(0)
        ->and((int) AccountBalance::query()->where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance'))->toBe(1_000);
});
