<?php

/**
 * POST /api/accounts/{uuid}/transactions/reverse could never reverse
 * anything: it called Money::fromFloat(), which does not exist, and it
 * called execute() on the workflow stub, which starts nothing. The reversal
 * must reach the workflow with the exact number of cents.
 */

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Workflows\TransactionReversalActivity;
use App\Domain\Asset\Models\Asset;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Workflow\WorkflowStub;

beforeEach(function (): void {
    config(['ledger.direct_funding_enabled' => true]);

    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::firstOrCreate(['code' => 'BTC'], ['name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8, 'is_active' => true]);

    $this->owner = User::factory()->create();
    $this->account = Account::factory()->create(['user_uuid' => $this->owner->uuid]);
    Sanctum::actingAs($this->owner, ['read', 'write', 'delete']);

    WorkflowStub::fake();
    WorkflowStub::mock(TransactionReversalActivity::class, ['reversed_amount' => 0]);
});

it('starts the reversal workflow with the exact number of cents', function (float $amount, int $cents): void {
    $this->postJson("/api/accounts/{$this->account->uuid}/transactions/reverse", [
        'amount'           => $amount,
        'asset_code'       => 'USD',
        'transaction_type' => 'debit',
        'reversal_reason'  => 'posted twice',
    ])->assertOk()->assertJsonPath('data.status', 'initiated');

    WorkflowStub::assertDispatched(
        TransactionReversalActivity::class,
        fn (AccountUuid $uuid, Money $money, string $type): bool => $uuid->getUuid() === $this->account->uuid
            && $money->getAmount() === $cents
            && $type === 'debit'
    );
})->with([
    '0.29 is 29 cents'      => [0.29, 29],
    '128.14 is 12814 cents' => [128.14, 12814],
]);

it('refuses an amount with more decimals than the asset has', function (): void {
    $this->postJson("/api/accounts/{$this->account->uuid}/transactions/reverse", [
        'amount'           => '0.295',
        'asset_code'       => 'USD',
        'transaction_type' => 'debit',
        'reversal_reason'  => 'posted twice',
    ])->assertStatus(422)->assertJsonValidationErrors(['amount']);

    WorkflowStub::assertNotDispatched(TransactionReversalActivity::class);
});

it('refuses an asset other than USD, which the reversal ledger does not hold', function (): void {
    $this->postJson("/api/accounts/{$this->account->uuid}/transactions/reverse", [
        'amount'           => 1.1,
        'asset_code'       => 'BTC',
        'transaction_type' => 'debit',
        'reversal_reason'  => 'posted twice',
    ])->assertStatus(422)->assertJsonValidationErrors(['asset_code']);

    WorkflowStub::assertNotDispatched(TransactionReversalActivity::class);
});
