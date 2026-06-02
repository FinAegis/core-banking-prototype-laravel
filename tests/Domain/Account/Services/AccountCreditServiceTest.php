<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Services\AccountCreditService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('credits an existing balance by the exact integer minor-unit amount', function () {
    $account = Account::factory()->create();
    AccountBalance::create([
        'account_uuid' => $account->uuid,
        'asset_code'   => 'USD',
        'balance'      => 100_000,
    ]);

    app(AccountCreditService::class)->credit($account->uuid, 25_000, 'USD');

    expect(AccountBalance::where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance'))
        ->toBe(125_000);
});

it('creates a balance row when none exists for the asset', function () {
    $account = Account::factory()->create();

    app(AccountCreditService::class)->credit($account->uuid, 5_000, 'EUR');

    expect(AccountBalance::where('account_uuid', $account->uuid)->where('asset_code', 'EUR')->value('balance'))
        ->toBe(5_000);
});

it('accumulates across multiple credits without precision loss', function () {
    $account = Account::factory()->create();

    $service = app(AccountCreditService::class);
    $service->credit($account->uuid, 1, 'USD');
    $service->credit($account->uuid, 2, 'USD');
    $service->credit($account->uuid, 99_999, 'USD');

    expect(AccountBalance::where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance'))
        ->toBe(100_002);
});

it('throws when the account does not exist', function () {
    expect(fn () => app(AccountCreditService::class)->credit('00000000-0000-0000-0000-000000000000', 100, 'USD'))
        ->toThrow(ModelNotFoundException::class);
});
