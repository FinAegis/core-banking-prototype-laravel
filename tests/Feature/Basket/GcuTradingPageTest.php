<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Asset\Models\Asset;
use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketValue;
use App\Domain\Wallet\Activities\ConvertAssetActivity;
use App\Models\User;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

// /gcu/trading read a BasketPrice model that does not exist and posted its
// forms to an unregistered route, so the page could not render. It now reads
// the GCU value from basket_values and its forms call /api/v2/gcu/buy|sell.

/**
 * @return array{User, Account}
 */
function gcuTradingPageUser(): array
{
    Asset::query()->updateOrCreate(['code' => 'GCU'], ['name' => 'Global Currency Unit', 'type' => 'custom', 'precision' => 2, 'is_active' => true]);
    if (! BasketAsset::query()->where('code', 'GCU')->exists()) {
        BasketAsset::factory()->create(['code' => 'GCU', 'name' => 'Global Currency Unit']);
    }

    $user = User::factory()->create(['email_verified_at' => now()]);
    $account = Account::factory()->create(['uuid' => (string) Str::uuid(), 'user_uuid' => $user->uuid, 'frozen' => false]);
    $account->balances()->updateOrCreate(['asset_code' => 'GCU'], ['balance' => 2_550]);
    $account->balances()->updateOrCreate(['asset_code' => 'USD'], ['balance' => 50_000]);

    return [$user, $account];
}

it('renders the GCU demo conversion page with the latest value, balances and conversions', function (): void {
    [$user, $account] = gcuTradingPageUser();
    BasketValue::factory()->create(['basket_asset_code' => 'GCU', 'value' => 1.0, 'calculated_at' => now()->subHour()]);
    BasketValue::factory()->create(['basket_asset_code' => 'GCU', 'value' => 1.25, 'calculated_at' => now()]);
    TransactionProjection::query()->create([
        'uuid'         => (string) Str::uuid(),
        'account_uuid' => $account->uuid,
        'asset_code'   => 'GCU',
        'amount'       => 1_234,
        'type'         => 'transfer_in',
        'subtype'      => 'conversion',
        'hash'         => str_repeat('a', 128),
        'status'       => 'completed',
    ]);

    $this->actingAs($user)
        ->get('/gcu/trading')
        ->assertOk()
        ->assertViewIs('gcu.trading')
        ->assertSee('$1.2500', false)
        ->assertSee('25.50', false)
        ->assertSee('500.00', false)
        ->assertSee('12.34 GCU', false)
        ->assertSee(url('/api/v2/gcu/buy'), false)
        ->assertSee(url('/api/v2/gcu/sell'), false);
});

it('renders the page when no GCU value has been calculated yet', function (): void {
    [$user] = gcuTradingPageUser();

    $this->actingAs($user)
        ->get('/gcu/trading')
        ->assertOk()
        ->assertSee('Not available yet')
        ->assertSee('No simulated conversions yet');
});

it('sends guests to log in', function (): void {
    $this->get('/gcu/trading')->assertRedirect(route('login'));
});

it('lets the signed-in web session call the buy endpoint the page uses', function (): void {
    [$user, $account] = gcuTradingPageUser();
    BasketValue::factory()->create(['basket_asset_code' => 'GCU', 'value' => 1.25, 'calculated_at' => now()]);
    WorkflowStub::fake();
    WorkflowStub::mock(ConvertAssetActivity::class, ['success' => true]);

    $this->actingAs($user)
        ->withHeader('Referer', url('/gcu/trading'))
        ->postJson('/api/v2/gcu/buy', ['amount' => '100', 'currency' => 'USD'])
        ->assertOk();

    WorkflowStub::assertDispatched(
        ConvertAssetActivity::class,
        fn (AccountUuid $uuid, string $from, string $to, int $amount): bool => (string) $uuid === $account->uuid && $from === 'USD' && $to === 'GCU' && $amount === 10_000
    );
});
