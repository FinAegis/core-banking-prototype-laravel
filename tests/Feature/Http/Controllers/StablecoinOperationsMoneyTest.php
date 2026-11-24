<?php

/**
 * The operator mint and burn forms must turn typed amounts into exact minor
 * units. The controller used (int) ($amount * 100) and float collateral
 * maths: 128.14 USDX was minted as 12813 cents, 256.28 USD of collateral
 * was locked as 25627, and a burn of 100.13 USDX recorded 10012 cents and
 * returned 14268 cents of collateral instead of 14269.
 */

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Stablecoin\Models\StablecoinOperation;
use App\Domain\Stablecoin\Workflows\Activities\BurnStablecoinActivity;
use App\Domain\Stablecoin\Workflows\Activities\CreatePositionActivity;
use App\Domain\Stablecoin\Workflows\Activities\LockCollateralActivity;
use App\Domain\Stablecoin\Workflows\Activities\MintStablecoinActivity;
use App\Domain\Stablecoin\Workflows\Activities\ReleaseCollateralActivity;
use App\Domain\Stablecoin\Workflows\Activities\UpdatePositionActivity;
use App\Models\User;
use Laravel\Pennant\Feature;
use Spatie\Permission\Models\Role;
use Workflow\WorkflowStub;

beforeEach(function (): void {
    config(['ledger.direct_funding_enabled' => true]);
    Feature::define('sub_product.stablecoins', fn (): bool => true);

    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::firstOrCreate(['code' => 'USDX'], ['name' => 'USD Stablecoin', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);

    Role::findOrCreate('stablecoin_operator', 'web');
    $this->operator = User::factory()->create();
    $this->operator->assignRole('stablecoin_operator');
    $this->account = Account::factory()->create(['user_uuid' => $this->operator->uuid]);

    WorkflowStub::fake();
    WorkflowStub::mock(CreatePositionActivity::class, ['position_uuid' => 'position-1']);
    WorkflowStub::mock(LockCollateralActivity::class, true);
    WorkflowStub::mock(MintStablecoinActivity::class, true);
    WorkflowStub::mock(BurnStablecoinActivity::class, true);
    WorkflowStub::mock(ReleaseCollateralActivity::class, true);
    WorkflowStub::mock(UpdatePositionActivity::class, true);
});

it('mints and locks the exact number of cents', function (): void {
    $this->actingAs($this->operator)->post('/wallet/stablecoin-operations/mint', [
        'stablecoin'         => 'USDX',
        'amount'             => '128.14',
        'collateral_asset'   => 'USD',
        'collateral_amount'  => '256.28',
        'recipient_account'  => $this->account->uuid,
        'reason'             => 'test mint',
        'authorization_code' => '123456',
    ]);

    $operation = StablecoinOperation::query()->where('type', 'mint')->sole();
    expect($operation->amount)->toBe(12814)
        ->and($operation->collateral_amount)->toBe(25628);
    WorkflowStub::assertDispatched(MintStablecoinActivity::class, fn (mixed $uuid, mixed $position, string $code, int $amount): bool => $amount === 12814);
    WorkflowStub::assertDispatched(LockCollateralActivity::class, fn (mixed $uuid, mixed $position, string $code, int $amount): bool => $amount === 25628);
});

it('refuses a mint amount with more decimals than the stablecoin has', function (): void {
    $this->actingAs($this->operator)->post('/wallet/stablecoin-operations/mint', [
        'stablecoin'         => 'USDX',
        'amount'             => '128.145',
        'collateral_asset'   => 'USD',
        'collateral_amount'  => '300',
        'recipient_account'  => $this->account->uuid,
        'reason'             => 'test mint',
        'authorization_code' => '123456',
    ])->assertSessionHasErrors(['amount']);

    expect(StablecoinOperation::query()->count())->toBe(0);
});

it('refuses collateral below the required amount by a single cent', function (): void {
    // 128.14 USDX at 150 % needs 192.21 USD.
    $this->actingAs($this->operator)->post('/wallet/stablecoin-operations/mint', [
        'stablecoin'         => 'USDX',
        'amount'             => '128.14',
        'collateral_asset'   => 'USD',
        'collateral_amount'  => '192.20',
        'recipient_account'  => $this->account->uuid,
        'reason'             => 'test mint',
        'authorization_code' => '123456',
    ])->assertSessionHasErrors(['collateral_amount']);

    expect(StablecoinOperation::query()->count())->toBe(0);
});

it('burns the exact number of cents and returns exact collateral', function (): void {
    AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USDX', 'balance' => 50000]);

    $this->actingAs($this->operator)->post('/wallet/stablecoin-operations/burn', [
        'stablecoin'         => 'USDX',
        'amount'             => '100.13',
        'source_account'     => $this->account->uuid,
        'return_collateral'  => true,
        'collateral_asset'   => 'USD',
        'reason'             => 'test burn',
        'authorization_code' => '123456',
    ]);

    // 100.13 USDX at 150 % is 150.195, rounded to 150.20 USD of collateral;
    // 95 % of it is 142.69 USD.
    $operation = StablecoinOperation::query()->where('type', 'burn')->sole();
    expect($operation->amount)->toBe(10013)
        ->and($operation->collateral_return)->toBe(14269);
    WorkflowStub::assertDispatched(BurnStablecoinActivity::class, fn (mixed $uuid, mixed $position, string $code, int $amount): bool => $amount === 10013);
    WorkflowStub::assertDispatched(ReleaseCollateralActivity::class, fn (mixed $uuid, mixed $position, mixed $code, int $amount): bool => $amount === 14269);
});

it('refuses a burn larger than the balance by a single cent', function (): void {
    AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USDX', 'balance' => 12813]);

    $this->actingAs($this->operator)->post('/wallet/stablecoin-operations/burn', [
        'stablecoin'         => 'USDX',
        'amount'             => '128.14',
        'source_account'     => $this->account->uuid,
        'return_collateral'  => false,
        'reason'             => 'test burn',
        'authorization_code' => '123456',
    ])->assertSessionHasErrors(['amount']);

    expect(StablecoinOperation::query()->count())->toBe(0);
});
