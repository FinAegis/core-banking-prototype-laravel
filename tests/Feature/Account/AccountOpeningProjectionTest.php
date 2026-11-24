<?php

declare(strict_types=1);

use App\Domain\Account\Aggregates\LedgerAggregate;
use App\Domain\Account\DataObjects\Account as AccountData;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Workflows\CreateAccountActivity;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Workflow\WorkflowStub;

/*
 * Opening an account starts the create-account workflow and answers at once.
 * When the workflow waits on the queue, the endpoint writes the accounts row
 * itself, and the queued AccountProjector writes the same row once a worker
 * runs the workflow. On the demo the worker's job then failed with a
 * duplicate key on accounts.uuid. When the workflow runs inline, the projector
 * writes the row first, and the BIAN endpoint failed on its own insert.
 */

it('lets the worker project an account the REST endpoint already wrote', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $uuid = (string) $this->postJson('/api/accounts', ['name' => 'Savings'])->assertCreated()->json('data.uuid');
    $before = Account::query()->where('uuid', $uuid)->firstOrFail()->getAttributes();
    $this->travel(5)->seconds();

    // The worker runs the workflow: AccountCreated is stored and projected.
    LedgerAggregate::retrieve($uuid)
        ->createAccount(new AccountData(name: 'Savings', userUuid: $user->uuid, uuid: $uuid))
        ->persist();

    expect(Account::query()->where('uuid', $uuid)->count())->toBe(1)
        ->and(Account::query()->where('uuid', $uuid)->firstOrFail()->getAttributes())->toBe($before);
});

it('opens a BIAN current account when the workflow already projected it', function (): void {
    WorkflowStub::fake();
    WorkflowStub::mock(CreateAccountActivity::class, function (mixed $context, AccountData $account): string {
        $uuid = (string) $account->getUuid();
        LedgerAggregate::retrieve($uuid)->createAccount($account)->persist();

        return $uuid;
    });

    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $uuid = (string) $this->postJson('/api/bian/current-account/initiate', [
        'customerReference' => $user->uuid,
        'accountName'       => 'Everyday',
        'accountType'       => 'current',
    ])->assertCreated()->json('currentAccountFulfillmentArrangement.crReferenceId');

    expect(Account::query()->where('uuid', $uuid)->count())->toBe(1)
        ->and(Account::query()->where('uuid', $uuid)->value('name'))->toBe('Everyday');
});
