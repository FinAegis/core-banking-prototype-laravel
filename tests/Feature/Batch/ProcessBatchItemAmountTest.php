<?php

/**
 * Batch transfer and conversion items carry major-unit amounts. The activity
 * converted them with (int) ($amount * 100), so an item of 128.14 USD was
 * checked against the balance as 12813 cents and a 0.29 USD conversion was
 * worked out on 28 cents.
 */

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Batch\Activities\ProcessBatchItemActivity;

beforeEach(function (): void {
    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
});

/**
 * @param  array<string, mixed>  $item
 * @return array<string, mixed>
 */
function batchItemCall(string $method, array $item): array
{
    $activity = (new ReflectionClass(ProcessBatchItemActivity::class))->newInstanceWithoutConstructor();
    $reflection = new ReflectionMethod(ProcessBatchItemActivity::class, $method);

    /** @var array<string, mixed> $result */
    $result = $reflection->invoke($activity, $item);

    return $result;
}

function batchAccountWithUsd(int $cents): Account
{
    $account = Account::factory()->create();
    AccountBalance::create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => $cents]);

    return $account;
}

it('refuses a transfer one cent above the balance', function (): void {
    $from = batchAccountWithUsd(12813);
    $to = Account::factory()->create();

    expect(fn () => batchItemCall('processTransfer', [
        'from_account' => $from->uuid,
        'to_account'   => $to->uuid,
        'amount'       => 128.14,
        'currency'     => 'USD',
    ]))->toThrow(InvalidArgumentException::class, 'Insufficient balance');
});

it('refuses a transfer amount with more decimals than the currency has', function (): void {
    $from = batchAccountWithUsd(100000);
    $to = Account::factory()->create();

    expect(fn () => batchItemCall('processTransfer', [
        'from_account' => $from->uuid,
        'to_account'   => $to->uuid,
        'amount'       => '1.005',
        'currency'     => 'USD',
    ]))->toThrow(InvalidArgumentException::class, 'at most 2 decimal places');
});

it('converts the exact number of cents', function (): void {
    $account = batchAccountWithUsd(29);

    $result = batchItemCall('processConversion', [
        'from_account'  => $account->uuid,
        'amount'        => 0.29,
        'from_currency' => 'USD',
        'to_currency'   => 'EUR',
    ]);

    // 29 cents at 0.92 is 26.68, rounded down to 26 euro cents.
    expect($result['converted_amount'])->toBe(26);
});

it('refuses a conversion one cent above the balance', function (): void {
    $account = batchAccountWithUsd(28);

    expect(fn () => batchItemCall('processConversion', [
        'from_account'  => $account->uuid,
        'amount'        => 0.29,
        'from_currency' => 'USD',
        'to_currency'   => 'EUR',
    ]))->toThrow(InvalidArgumentException::class, 'Insufficient balance');
});
