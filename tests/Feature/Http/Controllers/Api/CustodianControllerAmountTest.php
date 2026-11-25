<?php

/**
 * The REST custodian endpoints must convert between major and minor units
 * with the asset's precision. transfer used (int) ($amount * 100), which
 * sent 0.29 USD as 28 cents and 1.1 BTC as 110 satoshis; balance formatted
 * every asset as cents, so 0.1 BTC showed as "100,000.00".
 */

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Workflows\DepositAccountWorkflow;
use App\Domain\Asset\Models\Asset;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Workflows\Activities\InitiateCustodianTransferActivity;
use App\Domain\Custodian\Workflows\Activities\VerifyCustodianTransferActivity;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Workflow\WorkflowStub;

beforeEach(function (): void {
    config(['ledger.direct_funding_enabled' => true]);

    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::firstOrCreate(['code' => 'BTC'], ['name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8, 'is_active' => true]);

    $this->owner = User::factory()->create();
    $this->account = Account::factory()->create(['user_uuid' => $this->owner->uuid]);
    CustodianAccount::create([
        'account_uuid'         => $this->account->uuid,
        'custodian_name'       => 'mock',
        'custodian_account_id' => 'mock-account-1',
        'status'               => 'active',
        'is_primary'           => true,
    ]);
    Sanctum::actingAs($this->owner, ['read', 'write', 'delete']);

    WorkflowStub::fake();
    WorkflowStub::mock(InitiateCustodianTransferActivity::class, 'mock-tx-1');
    WorkflowStub::mock(VerifyCustodianTransferActivity::class, true);
    WorkflowStub::mock(DepositAccountWorkflow::class, true);
});

it('transfers the exact minor units of the asset', function (float $amount, string $assetCode, int $minor): void {
    $this->postJson('/api/custodians/mock/transfer', [
        'internal_account_uuid' => $this->account->uuid,
        'custodian_account_id'  => 'mock-account-1',
        'asset_code'            => $assetCode,
        'amount'                => $amount,
        'direction'             => 'deposit',
    ])->assertOk()->assertJsonPath('data.amount', $minor);

    WorkflowStub::assertDispatched(DepositAccountWorkflow::class, fn (AccountUuid $uuid, Money $money): bool => $money->getAmount() === $minor);
})->with([
    '0.29 USD is 29 cents'     => [0.29, 'USD', 29],
    '128.14 USD is 12814'      => [128.14, 'USD', 12814],
    '1.1 BTC is 110000000 sat' => [1.1, 'BTC', 110000000],
]);

it('refuses a transfer amount with more decimals than the asset has', function (): void {
    $this->postJson('/api/custodians/mock/transfer', [
        'internal_account_uuid' => $this->account->uuid,
        'custodian_account_id'  => 'mock-account-1',
        'asset_code'            => 'USD',
        'amount'                => '0.295',
        'direction'             => 'deposit',
    ])->assertStatus(422)->assertJsonValidationErrors(['amount']);

    WorkflowStub::assertNotDispatched(DepositAccountWorkflow::class);
});

it('formats a balance in the precision of the asset', function (string $assetCode, string $formatted): void {
    $this->getJson("/api/custodians/mock/balance?account_id=mock-account-1&asset_code={$assetCode}")
        ->assertOk()
        ->assertJsonPath('data.formatted_balance', $formatted);
})->with([
    // The mock custodian holds 1000000 cents of USD and 10000000 satoshis.
    'USD' => ['USD', '10,000.00'],
    'BTC' => ['BTC', '0.10000000'],
]);
