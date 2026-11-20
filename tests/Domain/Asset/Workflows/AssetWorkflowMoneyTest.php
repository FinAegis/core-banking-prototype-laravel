<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Asset\Workflows\Activities\DepositAssetActivity;
use App\Domain\Asset\Workflows\Activities\WithdrawAssetActivity;
use App\Domain\Asset\Workflows\AssetDepositWorkflow;
use App\Domain\Asset\Workflows\AssetWithdrawWorkflow;
use Illuminate\Contracts\Container\BindingResolutionException;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Workflow\Exceptions\TransitionNotFound;
use Workflow\Models\StoredWorkflow;
use Workflow\States\WorkflowFailedStatus;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;

/*
 * The asset deposit and withdraw workflows take the amount in minor units as an
 * int, while their activities take Money. They used to pass the int straight
 * through. The container then tried to build a Money for the activity and failed,
 * so every non-USD deposit and withdrawal failed and was retried for ever
 * (production workflow 39).
 */

it('passes the deposit amount to the activity as Money', function (): void {
    WorkflowStub::fake();
    WorkflowStub::mock(DepositAssetActivity::class, 'transaction-id');

    WorkflowStub::make(AssetDepositWorkflow::class)
        ->start(new AccountUuid('acct-1'), 'EUR', 1250, 'Top-up');

    WorkflowStub::assertDispatched(
        DepositAssetActivity::class,
        fn (AccountUuid $uuid, string $assetCode, mixed $money, ?string $description): bool => $money instanceof Money
            && $money->getAmount() === 1250
            && $assetCode === 'EUR'
            && $description === 'Top-up'
    );
});

it('passes the withdrawal amount to the activity as Money', function (): void {
    WorkflowStub::fake();
    WorkflowStub::mock(WithdrawAssetActivity::class, 'transaction-id');

    WorkflowStub::make(AssetWithdrawWorkflow::class)
        ->start(new AccountUuid('acct-1'), 'GBP', 900);

    WorkflowStub::assertDispatched(
        WithdrawAssetActivity::class,
        fn (AccountUuid $uuid, string $assetCode, mixed $money): bool => $money instanceof Money
            && $money->getAmount() === 900
            && $assetCode === 'GBP'
    );
});

it('keeps refusing an old deposit activity job that still carries an int amount', function (): void {
    // A job queued before the fix (like the one behind production workflow 39)
    // carries an int. Retrying it from the failed jobs must not credit anything,
    // even with direct funding on.
    config(['ledger.direct_funding_enabled' => true]);
    $account = Account::factory()->create(['frozen' => false]);
    $stored = StoredWorkflow::query()->whereKey(WorkflowStub::make(AssetDepositWorkflow::class)->id())->firstOrFail();
    $events = EloquentStoredEvent::query()->count();

    $activity = new DepositAssetActivity(0, now()->format('Y-m-d\TH:i:s.u\Z'), $stored, new AccountUuid($account->uuid), 'EUR', 1000, null);

    expect(fn () => $activity->handle())->toThrow(BindingResolutionException::class)
        ->and(EloquentStoredEvent::query()->count())->toBe($events)
        ->and($account->fresh()?->getBalance('EUR'))->toBe(0);
});

it('cannot resume a deposit workflow that an operator marked as failed', function (): void {
    $workflow = WorkflowStub::make(AssetDepositWorkflow::class);
    $stored = StoredWorkflow::query()->whereKey($workflow->id())->firstOrFail();
    $stored->status->transitionTo(WorkflowPendingStatus::class);
    $stored->status->transitionTo(WorkflowFailedStatus::class);

    expect(fn () => WorkflowStub::load($workflow->id())->resume())->toThrow(TransitionNotFound::class)
        ->and(WorkflowStub::load($workflow->id())->status())->toBe(WorkflowFailedStatus::class);
});

it('debits an asset balance through the withdraw activity', function (): void {
    // WithdrawAssetActivity called AccountUuid::toString(), which did not exist,
    // so every asset withdrawal failed before it checked the balance.
    $account = Account::factory()->create(['frozen' => false]);
    $account->balances()->updateOrCreate(['asset_code' => 'EUR'], ['balance' => 5000]);
    $stored = StoredWorkflow::query()->whereKey(WorkflowStub::make(AssetWithdrawWorkflow::class)->id())->firstOrFail();
    $events = EloquentStoredEvent::query()->count();

    $activity = new WithdrawAssetActivity(0, now()->format('Y-m-d\TH:i:s.u\Z'), $stored, new AccountUuid($account->uuid), 'EUR', new Money(1200), null);

    expect($activity->handle())->toBeString()
        ->and(EloquentStoredEvent::query()->count())->toBe($events + 1);
});

it('refuses an asset withdrawal above the balance', function (): void {
    $account = Account::factory()->create(['frozen' => false]);
    $account->balances()->updateOrCreate(['asset_code' => 'EUR'], ['balance' => 500]);
    $stored = StoredWorkflow::query()->whereKey(WorkflowStub::make(AssetWithdrawWorkflow::class)->id())->firstOrFail();
    $events = EloquentStoredEvent::query()->count();

    $activity = new WithdrawAssetActivity(0, now()->format('Y-m-d\TH:i:s.u\Z'), $stored, new AccountUuid($account->uuid), 'EUR', new Money(1200), null);

    expect(fn () => $activity->handle())->toThrow(Exception::class, 'Insufficient balance for EUR')
        ->and(EloquentStoredEvent::query()->count())->toBe($events);
});
