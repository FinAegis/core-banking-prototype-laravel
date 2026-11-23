<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketValue;
use App\Domain\Wallet\Activities\ConvertAssetActivity;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Workflow\WorkflowStub;

/*
 * GCU buy and sell start a wallet conversion with an amount in minor units.
 * Buy used float maths and sell multiplied by 10 000 although GCU has two
 * decimal places, so selling 10 GCU asked to convert 1 000 GCU. Both also
 * compared the amount the user typed with a balance held in minor units.
 */

/**
 * @param  array<string, int>  $balances
 * @return array{User, Account}
 */
function gcuTrader(array $balances): array
{
    Asset::query()->updateOrCreate(['code' => 'GCU'], ['name' => 'Global Currency Unit', 'type' => 'custom', 'precision' => 2, 'is_active' => true]);
    if (! BasketAsset::query()->where('code', 'GCU')->exists()) {
        BasketAsset::factory()->create(['code' => 'GCU', 'name' => 'Global Currency Unit']);
    }
    $user = User::factory()->create();
    $account = Account::factory()->create(['uuid' => (string) Str::uuid(), 'user_uuid' => $user->uuid, 'frozen' => false]);
    foreach ($balances as $asset => $balance) {
        $account->balances()->updateOrCreate(['asset_code' => $asset], ['balance' => $balance]);
    }
    BasketValue::factory()->create(['basket_asset_code' => 'GCU', 'value' => 1.25, 'calculated_at' => now()]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    WorkflowStub::fake();
    WorkflowStub::mock(ConvertAssetActivity::class, ['success' => true]);

    return [$user, $account];
}

it('sells GCU in its own minor units', function (): void {
    [, $account] = gcuTrader(['GCU' => 5_000]);

    $this->postJson('/api/v2/gcu/sell', ['amount' => 10, 'currency' => 'USD', 'account_uuid' => $account->uuid])->assertOk();

    WorkflowStub::assertDispatched(
        ConvertAssetActivity::class,
        fn (AccountUuid $uuid, string $from, string $to, int $amount): bool => $from === 'GCU' && $to === 'USD' && $amount === 1_000
    );
});

it('buys GCU with the spent amount in the currency minor units', function (): void {
    // (int) (128.14 * 100) is 12 813 in floating point.
    [, $account] = gcuTrader(['USD' => 50_000]);

    $this->postJson('/api/v2/gcu/buy', ['amount' => '128.14', 'currency' => 'USD', 'account_uuid' => $account->uuid])->assertOk();

    WorkflowStub::assertDispatched(
        ConvertAssetActivity::class,
        fn (AccountUuid $uuid, string $from, string $to, int $amount): bool => $from === 'USD' && $to === 'GCU' && $amount === 12_814
    );
});

it('refuses a GCU purchase above the balance held in minor units', function (): void {
    // 50.00 USD is 5 000 minor units, which used to pass a check against "100".
    [, $account] = gcuTrader(['USD' => 5_000]);

    $this->postJson('/api/v2/gcu/buy', ['amount' => 100, 'currency' => 'USD', 'account_uuid' => $account->uuid])->assertStatus(422);

    WorkflowStub::assertNotDispatched(ConvertAssetActivity::class);
});

it('books a GCU purchase at the quoted price after the fee', function (): void {
    // GCU is worth 1.25 USD, so 1 USD buys 0.8 GCU, and the 1 % fee leaves 0.792.
    // The conversion used to read the rate table instead, which knows no GCU price.
    [, $account] = gcuTrader(['USD' => 50_000]);

    $this->postJson('/api/v2/gcu/buy', ['amount' => '128.14', 'currency' => 'USD', 'account_uuid' => $account->uuid])
        ->assertOk()
        ->assertJsonPath('data.received_amount', 101.48);

    WorkflowStub::assertDispatched(
        ConvertAssetActivity::class,
        fn (AccountUuid $uuid, string $from, string $to, int $amount, ?string $rate = null): bool => $amount === 12_814 && $rate === '0.7920000000'
    );
});

it('books a GCU sale at the quoted price after the fee', function (): void {
    // 1 GCU is 1.25 USD; after the 1 % fee each GCU pays 1.2375 USD.
    [, $account] = gcuTrader(['GCU' => 5_000]);

    $this->postJson('/api/v2/gcu/sell', ['amount' => 10, 'currency' => 'USD', 'account_uuid' => $account->uuid])
        ->assertOk()
        ->assertJsonPath('data.received_amount', 12.37);

    WorkflowStub::assertDispatched(
        ConvertAssetActivity::class,
        fn (AccountUuid $uuid, string $from, string $to, int $amount, ?string $rate = null): bool => $amount === 1_000 && $rate === '1.2375000000'
    );
});
