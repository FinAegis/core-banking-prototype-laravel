<?php

/**
 * Admin deposit / withdraw table actions on AccountResource must turn the
 * typed dollar amount into exact cents. A float product truncates amounts
 * such as 128.14 (128.14 * 100 = 12813.999…, cast to 12813).
 */

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Services\AccountService;
use App\Domain\Asset\Models\Asset;
use App\Filament\Admin\Resources\AccountResource\Pages\ListAccounts;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Mockery\MockInterface;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setServingStatus(true);

    $this->actingAs(User::factory()->withAdminRole()->createOne());

    // The deposit action is direct ledger funding and only shows while it is on.
    config(['ledger.direct_funding_enabled' => true]);

    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);

    $this->account = Account::factory()->createOne(['frozen' => false]);
    // The withdraw action only shows for a positive USD balance.
    AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USD', 'balance' => 100000]);
});

/**
 * Bind an AccountService mock that records the amount the action passes.
 *
 * @return ArrayObject<int, mixed>
 */
function accountMoneyActionsCapture(string $method): ArrayObject
{
    /** @var ArrayObject<int, mixed> $calls */
    $calls = new ArrayObject();

    /** @var AccountService&MockInterface $service */
    $service = Mockery::mock(AccountService::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $service->shouldReceive($method);
    $expectation->andReturnUsing(function (mixed $uuid, mixed $amount) use ($calls): void {
        $calls->append($amount);
    });

    app()->instance(AccountService::class, $service);

    return $calls;
}

it('deposits the exact number of cents', function (float|string $amount, int $cents): void {
    $calls = accountMoneyActionsCapture('deposit');

    Livewire::test(ListAccounts::class)
        ->callTableAction('deposit', $this->account, data: ['amount' => $amount])
        ->assertHasNoTableActionErrors();

    expect($calls->getArrayCopy())->toBe([$cents]);
})->with([
    'float 128.14'  => [128.14, 12814],
    'string 128.14' => ['128.14', 12814],
    'float 0.29'    => [0.29, 29],
    'whole 50'      => ['50', 5000],
]);

it('withdraws the exact number of cents', function (float|string $amount, int $cents): void {
    $calls = accountMoneyActionsCapture('withdraw');

    Livewire::test(ListAccounts::class)
        ->callTableAction('withdraw', $this->account, data: ['amount' => $amount])
        ->assertHasNoTableActionErrors();

    expect($calls->getArrayCopy())->toBe([$cents]);
})->with([
    'float 128.14'  => [128.14, 12814],
    'string 128.14' => ['128.14', 12814],
    'float 0.29'    => [0.29, 29],
    'whole 50'      => ['50', 5000],
]);

it('filters balances on the exact number of cents', function (int $balance, string $operator, string $amount, bool $seesMatch): void {
    // The filter compared against $amount * 100, a float. The comparison
    // is now made in exact cents, up to the largest balance the column
    // holds (a signed 32-bit integer: 21,474,836.47).
    $match = Account::factory()->createOne(['balance' => $balance]);

    Livewire::test(ListAccounts::class)
        ->filterTable('balance', ['balance_operator' => $operator, 'balance_amount' => $amount])
        ->{$seesMatch ? 'assertCanSeeTableRecords' : 'assertCanNotSeeTableRecords'}([$match]);
})->with([
    'equal to 128.14'            => [12814, '=', '128.14', true],
    'less than 128.14'           => [12814, '<', '128.14', false],
    'equal to 21,474,836.47'     => [2147483647, '=', '21474836.47', true],
    'greater than 21,474,836.46' => [2147483647, '>', '21474836.46', true],
    'equal to 128.145'           => [12814, '=', '128.145', false],
    'less than 128.145'          => [12814, '<', '128.145', true],
]);
