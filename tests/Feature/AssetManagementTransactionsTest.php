<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Asset\Models\Asset;
use App\Http\Controllers\AssetManagementController;
use App\Models\User;

// transaction_projections keeps the asset in asset_code; there is no
// currency column. The asset page filtered on currency = $asset->symbol, so
// MySQL rejected the query and SQLite returned no transactions.

it('lists the user transactions of the asset shown', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    $asset = Asset::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]
    );

    TransactionProjection::factory()->create(['account_uuid' => $account->uuid, 'asset_code' => 'EUR']);
    TransactionProjection::factory()->create(['account_uuid' => $account->uuid, 'asset_code' => 'USD']);

    $controller = app(AssetManagementController::class);
    $transactions = (new ReflectionMethod($controller, 'getAssetTransactions'))->invoke($controller, $user, $asset);

    expect($transactions)->toHaveCount(1)
        ->and($transactions->first()?->asset_code)->toBe('EUR');
});
