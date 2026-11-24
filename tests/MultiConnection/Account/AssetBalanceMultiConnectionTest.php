<?php

declare(strict_types=1);

namespace Tests\MultiConnection\Account;

use App\Domain\Account\Aggregates\AssetTransactionAggregate;
use App\Domain\Account\Events\AssetBalanceAdded;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Repositories\TransactionRepository;
use App\Domain\Asset\Models\Asset;
use Illuminate\Support\Str;
use Spatie\EventSourcing\Projectionist;
use Spatie\EventSourcing\StoredEvents\HandleStoredEventJob;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

// AccountProjector is the only projector of AssetBalanceAdded and
// AssetBalanceSubtracted. It locks the balance row and claims the stored event
// in one transaction on the balances' (tenant) connection. This runs it against
// real MySQL with separate sessions.
beforeEach(function (): void {
    Asset::factory()->create(['code' => 'USD', 'name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
});

function mcUsdBalance(Account $account): int
{
    return (int) AccountBalance::query()->where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance');
}

it('credits and debits once, and a redelivered credit changes nothing', function (): void {
    $account = Account::factory()->create(['uuid' => (string) Str::uuid()]);

    AssetTransactionAggregate::retrieve($account->uuid)->credit('USD', 1_000)->debit('USD', 300)->persist();

    /** @var StoredEvent $credit */
    $credit = app(TransactionRepository::class)
        ->retrieveAll($account->uuid)
        ->first(fn (StoredEvent $storedEvent): bool => $storedEvent->event instanceof AssetBalanceAdded);
    HandleStoredEventJob::createForEvent($credit, [])->handle(app(Projectionist::class));

    expect(mcUsdBalance($account))->toBe(700);
});
