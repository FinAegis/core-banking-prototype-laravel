<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Workflows\DepositAccountWorkflow;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Workflows\AssetDepositWorkflow;
use App\Domain\Asset\Workflows\AssetWithdrawWorkflow;
use App\Domain\Wallet\Workflows\WalletTransferWorkflow;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Workflow\Models\StoredWorkflow;

/*
| The v2 transfer, deposit and withdraw endpoints take the amount in major
| units ("128.14" USD, "1.1" ETH) and book it in the asset's minor units.
| The conversion must be exact decimal arithmetic. Float maths books
| 1.1 ETH (18 decimals) as 1 100 000 000 000 000 128 wei instead of
| 1 100 000 000 000 000 000. An amount with more decimals than the asset has
| is refused, because it cannot be booked exactly.
*/

beforeEach(function (): void {
    config(['ledger.direct_funding_enabled' => true]);

    $this->user = User::factory()->create();
    $this->recipient = User::factory()->create();
    $this->account = Account::factory()->create(['user_uuid' => $this->user->uuid, 'frozen' => false]);
    $this->otherAccount = Account::factory()->create(['user_uuid' => $this->recipient->uuid, 'frozen' => false]);

    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::updateOrCreate(['code' => 'ETH'], ['name' => 'Ethereum', 'type' => 'crypto', 'precision' => 18, 'is_active' => true]);

    AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USD', 'balance' => 1000000]);
    AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'ETH', 'balance' => 5000000000000000000]);

    Sanctum::actingAs($this->user, ['read', 'write', 'delete']);
});

/**
 * Arguments of every workflow of the given class that the request started.
 *
 * @return array<int, array<int, mixed>>
 */
function startedWorkflows(string $class): array
{
    return StoredWorkflow::query()
        ->where('class', $class)
        ->get()
        ->map(fn (StoredWorkflow $workflow): array => $workflow->workflowArguments())
        ->values()
        ->all();
}

it('books a USD transfer of 128.14 as 12 814 cents', function (): void {
    $this->postJson('/api/v2/transfers', [
        'from_account' => $this->account->uuid,
        'to_account'   => $this->otherAccount->uuid,
        'amount'       => 128.14,
        'asset_code'   => 'USD',
    ])->assertStatus(201);

    $started = startedWorkflows(WalletTransferWorkflow::class);
    expect($started)->toHaveCount(1)
        ->and($started[0][3])->toBe(12814);
});

it('books an ETH transfer of 1.1 as exactly 1.1 × 10^18 wei', function (): void {
    $this->postJson('/api/v2/transfers', [
        'from_account' => $this->account->uuid,
        'to_account'   => $this->otherAccount->uuid,
        'amount'       => '1.1',
        'asset_code'   => 'ETH',
    ])->assertStatus(201);

    $started = startedWorkflows(WalletTransferWorkflow::class);
    expect($started)->toHaveCount(1)
        ->and($started[0][3])->toBe(1100000000000000000);
});

it('refuses a USD transfer with more than two decimals and starts nothing', function (): void {
    $response = $this->postJson('/api/v2/transfers', [
        'from_account' => $this->account->uuid,
        'to_account'   => $this->otherAccount->uuid,
        'amount'       => 10.005,
        'asset_code'   => 'USD',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('amount');
    expect(startedWorkflows(WalletTransferWorkflow::class))->toBe([]);
});

it('books a USD deposit of 128.14 as 12 814 cents', function (): void {
    $this->postJson("/api/v2/accounts/{$this->account->uuid}/deposit", [
        'amount'     => '128.14',
        'asset_code' => 'USD',
    ])->assertOk();

    $started = startedWorkflows(DepositAccountWorkflow::class);
    expect($started)->toHaveCount(1);
    $money = $started[0][1];
    assert($money instanceof Money);
    expect($money->getAmount())->toBe(12814);
});

it('books an ETH deposit and withdrawal of 1.1 exactly', function (): void {
    $this->postJson("/api/v2/accounts/{$this->account->uuid}/deposit", ['amount' => '1.1', 'asset_code' => 'ETH'])
        ->assertOk();
    $this->postJson("/api/v2/accounts/{$this->account->uuid}/withdraw", ['amount' => '1.1', 'asset_code' => 'ETH'])
        ->assertOk();

    $deposits = startedWorkflows(AssetDepositWorkflow::class);
    $withdrawals = startedWorkflows(AssetWithdrawWorkflow::class);
    expect($deposits)->toHaveCount(1)
        ->and($deposits[0][2])->toBe(1100000000000000000)
        ->and($withdrawals)->toHaveCount(1)
        ->and($withdrawals[0][2])->toBe(1100000000000000000);
});

it('refuses a deposit or withdrawal with more decimals than the asset has', function (string $operation): void {
    $response = $this->postJson("/api/v2/accounts/{$this->account->uuid}/{$operation}", [
        'amount'     => 10.005,
        'asset_code' => 'USD',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('amount');
    expect(StoredWorkflow::query()->count())->toBe(0);
})->with(['deposit', 'withdraw']);
