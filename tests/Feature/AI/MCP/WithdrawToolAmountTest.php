<?php

/**
 * The MCP account.withdraw tool checked the balance with
 * (int) ($amount * 100): 0.29 USD counted as 28 cents, so a balance of 28
 * cents passed the check, and 1.1 BTC counted as 110 satoshis.
 */

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Services\AccountService;
use App\Domain\AI\MCP\Tools\Account\WithdrawTool;
use App\Domain\Asset\Models\Asset;
use App\Models\User;

beforeEach(function (): void {
    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::firstOrCreate(['code' => 'BTC'], ['name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8, 'is_active' => true]);

    $this->user = User::factory()->create();
    $this->account = Account::factory()->create(['user_uuid' => $this->user->uuid]);
    $this->actingAs($this->user);
});

function withdrawToolFor(): WithdrawTool
{
    /** @var AccountService $service */
    $service = Mockery::mock(AccountService::class);

    return new WithdrawTool($service);
}

it('refuses a withdrawal one minor unit above the balance', function (string $asset, int $balance, float $amount, string $available): void {
    AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => $asset, 'balance' => $balance]);

    $result = withdrawToolFor()->execute([
        'account_uuid' => $this->account->uuid,
        'amount'       => $amount,
        'currency'     => $asset,
    ]);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->getError())->toBe("Insufficient balance. Available: {$available} {$asset}");
})->with([
    '0.29 USD against 28 cents' => ['USD', 28, 0.29, '0.28'],
    '1.1 BTC against 0.1 BTC'   => ['BTC', 10000000, 1.1, '0.10000000'],
]);

it('refuses an amount with more decimals than the asset has', function (): void {
    AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USD', 'balance' => 100000]);

    $result = withdrawToolFor()->execute([
        'account_uuid' => $this->account->uuid,
        'amount'       => '1.005',
        'currency'     => 'USD',
    ]);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->getError())->toBe('The amount can have at most 2 decimal places for USD.');
});
