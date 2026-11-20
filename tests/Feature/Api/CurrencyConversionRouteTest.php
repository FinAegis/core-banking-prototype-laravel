<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Workflows\WithdrawAccountWorkflow;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Asset\Workflows\AssetDepositWorkflow;
use App\Domain\Asset\Workflows\AssetWithdrawWorkflow;
use App\Models\User;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Workflow\WorkflowStub;

beforeEach(function (): void {
    config(['ledger.direct_funding_enabled' => true]);
    Feature::define('sub_product.exchange', fn (): bool => true);

    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    ExchangeRate::create(['from_asset_code' => 'USD', 'to_asset_code' => 'EUR', 'rate' => 0.92, 'source' => 'test', 'valid_at' => now()->subMinute(), 'is_active' => true]);
    ExchangeRate::create(['from_asset_code' => 'EUR', 'to_asset_code' => 'USD', 'rate' => 1.08, 'source' => 'test', 'valid_at' => now()->subMinute(), 'is_active' => true]);

    WorkflowStub::fake();
});

it('debits the USD source before it credits the target asset', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->withBalance(5000)->create(['user_uuid' => $user->uuid, 'frozen' => false]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    WorkflowStub::mock(WithdrawAccountWorkflow::class, true);
    WorkflowStub::mock(AssetDepositWorkflow::class, 'credit-id');

    $this->postJson('/api/exchange/convert', [
        'account_uuid'  => $account->uuid,
        'from_currency' => 'USD',
        'to_currency'   => 'EUR',
        'amount'        => 10,
    ])->assertOk()->assertJsonPath('data.to_amount', 9.2);

    WorkflowStub::assertDispatched(WithdrawAccountWorkflow::class, fn (AccountUuid $uuid, Money $money): bool => $uuid->getUuid() === $account->uuid && $money->getAmount() === 1000);
    WorkflowStub::assertDispatched(AssetDepositWorkflow::class, fn (AccountUuid $uuid, string $assetCode, int $amount): bool => $assetCode === 'EUR' && $amount === 920);
});

it('does not credit the target asset when the debit is refused', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid, 'frozen' => false]);
    Account::query()->whereKey($account->getKey())->firstOrFail()->balances()->updateOrCreate(['asset_code' => 'EUR'], ['balance' => 5000]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $debitAttempted = false;
    WorkflowStub::mock(AssetWithdrawWorkflow::class, function () use (&$debitAttempted): never {
        $debitAttempted = true;

        throw new RuntimeException('Insufficient balance for EUR.');
    });
    WorkflowStub::mock(AssetDepositWorkflow::class, 'credit-id');

    // The workflow runs synchronously under the fake, so the refused debit
    // surfaces as the endpoint's conversion error.
    $this->postJson('/api/exchange/convert', [
        'account_uuid'  => $account->uuid,
        'from_currency' => 'EUR',
        'to_currency'   => 'USD',
        'amount'        => 10,
    ])->assertStatus(422)->assertJsonPath('error', 'CONVERSION_FAILED');

    expect($debitAttempted)->toBeTrue();
    WorkflowStub::assertNotDispatched(AssetDepositWorkflow::class);
});
