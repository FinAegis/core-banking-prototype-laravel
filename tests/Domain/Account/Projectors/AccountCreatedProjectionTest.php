<?php

declare(strict_types=1);

use App\Domain\Account\Aggregates\LedgerAggregate;
use App\Domain\Account\DataObjects\Account as AccountData;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Repositories\LedgerRepository;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\EventSourcing\Projectionist;
use Spatie\EventSourcing\StoredEvents\HandleStoredEventJob;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

/*
 * The queued AccountProjector writes the accounts row for AccountCreated. The
 * row can already be there: the account endpoints write it themselves when the
 * create-account workflow is still waiting on the queue, and a queue can deliver
 * the same stored event twice. Both used to stop the queued job with a
 * duplicate key on accounts.uuid. There must be one row, left as it was.
 */

function recordAccountCreatedEvent(string $uuid, string $userUuid, string $name): void
{
    LedgerAggregate::retrieve($uuid)
        ->createAccount(new AccountData(name: $name, userUuid: $userUuid, uuid: $uuid))
        ->persist();
}

/** @return array<string, mixed> */
function accountRowFor(string $uuid): array
{
    return Account::query()->where('uuid', $uuid)->firstOrFail()->getAttributes();
}

it('keeps one unchanged account when the queued handler runs the same AccountCreated again', function (): void {
    $user = User::factory()->create();
    $uuid = (string) Str::uuid();

    recordAccountCreatedEvent($uuid, $user->uuid, 'Main account');
    $before = accountRowFor($uuid);
    $this->travel(5)->seconds();

    /** @var StoredEvent $storedEvent */
    $storedEvent = app(LedgerRepository::class)->retrieveAll($uuid)->first();
    HandleStoredEventJob::createForEvent($storedEvent, [])->handle(app(Projectionist::class));

    expect(Account::query()->where('uuid', $uuid)->count())->toBe(1)
        ->and(accountRowFor($uuid))->toBe($before);
});

it('keeps the account row an endpoint already wrote when the queued projector handles AccountCreated', function (): void {
    $user = User::factory()->create();
    $uuid = (string) Str::uuid();

    // What the account endpoints write while the workflow waits on the queue.
    Account::query()->create(['uuid' => $uuid, 'user_uuid' => $user->uuid, 'name' => 'Main account', 'balance' => 0]);
    $before = accountRowFor($uuid);
    $this->travel(5)->seconds();

    // The worker runs the workflow; with the sync queue the projector job runs here.
    recordAccountCreatedEvent($uuid, $user->uuid, 'Main account');

    expect(Account::query()->where('uuid', $uuid)->count())->toBe(1)
        ->and(accountRowFor($uuid))->toBe($before);
});
