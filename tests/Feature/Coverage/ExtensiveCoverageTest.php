<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Hash;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\Turnover;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\User\Values\UserRoles;
use App\Models\User;
use App\Values\EventQueues;
use Illuminate\Support\Str;

// Test existing model class instantiation
it('can instantiate existing core models', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $asset = Asset::factory()->create([
        'code' => 'TST' . strtoupper(Str::random(3)),
    ]);
    $exchangeRate = ExchangeRate::factory()->create();

    expect($user)->toBeInstanceOf(User::class);
    expect($account)->toBeInstanceOf(Account::class);
    expect($asset)->toBeInstanceOf(Asset::class);
    expect($exchangeRate)->toBeInstanceOf(ExchangeRate::class);
});

// Test data object classes and their methods
it('can instantiate and use data objects', function () {
    $money = new Money(10000);
    $hash = new Hash(str_repeat('a', 128));
    $accountUuid = new AccountUuid('12345678-1234-1234-1234-123456789012');

    expect($money)->toBeInstanceOf(Money::class);
    expect($money->getAmount())->toBe(10000);
    expect($money->invert()->getAmount())->toBe(-10000);

    expect($hash)->toBeInstanceOf(Hash::class);
    expect($hash->getHash())->toBe(str_repeat('a', 128));

    expect($accountUuid)->toBeInstanceOf(AccountUuid::class);
    expect($accountUuid->getUuid())->toBe('12345678-1234-1234-1234-123456789012');
    expect($accountUuid->toArray())->toHaveKey('uuid');
});

// Test enum cases and values comprehensively
it('can test enum values and cases comprehensively', function () {
    // Test UserRoles enum
    $userRolesCases = UserRoles::cases();
    expect($userRolesCases)->toBeArray();
    expect(count($userRolesCases))->toBeGreaterThan(0);

    foreach ($userRolesCases as $role) {
        expect($role)->toBeInstanceOf(UserRoles::class);
        expect($role->value)->toBeString();
    }

    // Test EventQueues enum
    $eventQueuesCases = EventQueues::cases();
    expect($eventQueuesCases)->toBeArray();
    expect(count($eventQueuesCases))->toBeGreaterThan(0);

    foreach ($eventQueuesCases as $queue) {
        expect($queue)->toBeInstanceOf(EventQueues::class);
        expect($queue->value)->toBeString();
    }

    // Test default method
    expect(EventQueues::default())->toBe(EventQueues::EVENTS);
});

// Test existing factory states
it('can test existing factory states', function () {
    // Test Account factory states that exist
    $richAccount = Account::factory()->withBalance(100000)->create();
    $zeroAccount = Account::factory()->zeroBalance()->create();

    expect($richAccount->balance)->toBe(100000);
    expect($zeroAccount->balance)->toBe(0);

    // Test Asset factory states that exist
    $fiatAsset = Asset::factory()->fiat()->create();
    $cryptoAsset = Asset::factory()->crypto()->create();
    $commodityAsset = Asset::factory()->commodity()->create();

    expect($fiatAsset->type)->toBe('fiat');
    expect($cryptoAsset->type)->toBe('crypto');
    expect($commodityAsset->type)->toBe('commodity');
});

// Test model relationships that exist
it('can test existing model relationships', function () {
    $user = User::factory()->create();
    $account = Account::factory()->forUser($user)->create();
    $asset = Asset::factory()->create([
        'code' => 'TST' . strtoupper(Str::random(3)),
    ]);
    $balance = AccountBalance::factory()->create([
        'account_uuid' => $account->uuid,
        'asset_code'   => $asset->code,
    ]);

    // Test Account relationships
    expect($account->user)->toBeInstanceOf(User::class);
    expect($account->user->id)->toBe($user->id);
    expect($account->balances)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);

    // Test Balance relationships
    expect($balance->account)->toBeInstanceOf(Account::class);
    expect($balance->asset)->toBeInstanceOf(Asset::class);
});

// Test asset model methods comprehensively
it('can test asset model methods extensively', function () {
    $fiatAsset = Asset::factory()->fiat()->active()->create();
    $cryptoAsset = Asset::factory()->crypto()->active()->create();
    $commodityAsset = Asset::factory()->commodity()->active()->create();
    $inactiveAsset = Asset::factory()->inactive()->create();

    // Test type checking methods
    expect($fiatAsset->isFiat())->toBeTrue();
    expect($fiatAsset->isCrypto())->toBeFalse();
    expect($fiatAsset->isCommodity())->toBeFalse();

    expect($cryptoAsset->isFiat())->toBeFalse();
    expect($cryptoAsset->isCrypto())->toBeTrue();
    expect($cryptoAsset->isCommodity())->toBeFalse();

    expect($commodityAsset->isFiat())->toBeFalse();
    expect($commodityAsset->isCrypto())->toBeFalse();
    expect($commodityAsset->isCommodity())->toBeTrue();

    // Test active status
    expect($fiatAsset->is_active)->toBeTrue();
    expect($inactiveAsset->is_active)->toBeFalse();

    // Test precision values
    expect($fiatAsset->precision)->toBeInt();
    expect($cryptoAsset->precision)->toBeInt();
    expect($commodityAsset->precision)->toBeInt();
});

// Test exchange rate model methods
it('can test exchange rate model methods', function () {
    $fromAsset = Asset::factory()->create(['code' => 'FROM']);
    $toAsset = Asset::factory()->create(['code' => 'TO']);

    $activeRate = ExchangeRate::factory()->create([
        'from_asset_code' => 'FROM',
        'to_asset_code'   => 'TO',
        'rate'            => 1.5,
        'is_active'       => true,
    ]);

    $inactiveRate = ExchangeRate::factory()->create([
        'is_active' => false,
    ]);

    // Test basic properties
    expect($activeRate->from_asset_code)->toBe('FROM');
    expect($activeRate->to_asset_code)->toBe('TO');
    expect($activeRate->rate)->toBe('1.5000000000');
    expect($activeRate->is_active)->toBeTrue();
    expect($inactiveRate->is_active)->toBeFalse();

    // Test relationships
    expect($activeRate->fromAsset)->toBeInstanceOf(Asset::class);
    expect($activeRate->toAsset)->toBeInstanceOf(Asset::class);

    // Test timestamps exist
    expect($activeRate->valid_at)->toBeInstanceOf(Carbon\Carbon::class);
});

// Test account balance model methods
it('can test account balance model methods extensively', function () {
    $account = Account::factory()->create();
    // Create asset with unique code to avoid conflicts with seeded data
    $asset = Asset::factory()->create([
        'code' => 'TST' . strtoupper(Str::random(3)),
    ]);

    $balance = AccountBalance::factory()->create([
        'account_uuid' => $account->uuid,
        'asset_code'   => $asset->code,
        'balance'      => 50000, // $500.00
    ]);

    // Test basic properties
    expect($balance->account_uuid)->toBe($account->uuid);
    expect($balance->asset_code)->toBe($asset->code);
    expect($balance->balance)->toBe(50000);

    // Test balance operations
    expect($balance->hasSufficientBalance(30000))->toBeTrue();
    expect($balance->hasSufficientBalance(60000))->toBeFalse();

    // Test credit/debit operations
    $originalBalance = $balance->balance;
    $balance->credit(10000);
    expect($balance->balance)->toBe($originalBalance + 10000);

    $balance->debit(5000);
    expect($balance->balance)->toBe($originalBalance + 10000 - 5000);
});

// Test turnover model class exists
it('can test turnover model exists', function () {
    expect(class_exists(Turnover::class))->toBeTrue();
});

// Test additional application classes that exist
it('can test existing application classes', function () {
    // Test that key API controllers exist
    expect(class_exists(App\Http\Controllers\Api\AccountController::class))->toBeTrue();
    expect(class_exists(App\Http\Controllers\Api\AssetController::class))->toBeTrue();
    expect(class_exists(App\Http\Controllers\Api\ExchangeRateController::class))->toBeTrue();
    expect(class_exists(App\Http\Controllers\Api\AccountBalanceController::class))->toBeTrue();

    // Test that BIAN controllers exist
    expect(class_exists(App\Http\Controllers\Api\BIAN\PaymentInitiationController::class))->toBeTrue();
    expect(class_exists(App\Http\Controllers\Api\BIAN\CurrentAccountController::class))->toBeTrue();

    // Test that Filament resources exist
    expect(class_exists(App\Filament\Admin\Resources\AssetResource::class))->toBeTrue();
    expect(class_exists(App\Filament\Admin\Resources\ExchangeRateResource::class))->toBeTrue();
    expect(class_exists(App\Filament\Admin\Resources\AccountResource::class))->toBeTrue();
    expect(class_exists(App\Filament\Admin\Resources\UserResource::class))->toBeTrue();

    // Test that domain services exist
    expect(class_exists(App\Domain\Account\Services\AccountService::class))->toBeTrue();
    expect(class_exists(App\Domain\Asset\Services\ExchangeRateService::class))->toBeTrue();
});

// Test view components
it('can test existing view components', function () {
    $appLayout = new App\View\Components\AppLayout();
    $guestLayout = new App\View\Components\GuestLayout();

    expect($appLayout)->toBeInstanceOf(App\View\Components\AppLayout::class);
    expect($guestLayout)->toBeInstanceOf(App\View\Components\GuestLayout::class);

    // Test Laravel core middleware exist
    expect(class_exists(Illuminate\Auth\Middleware\Authenticate::class))->toBeTrue();
    expect(class_exists(Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class))->toBeTrue();
});

// Test command classes that exist
it('can test existing console command classes', function () {
    expect(class_exists(App\Domain\Account\Console\Commands\VerifyTransactionHashes::class))->toBeTrue();
});

// Test domain events
it('can test existing domain event classes', function () {
    expect(class_exists(App\Domain\Account\Events\MoneyAdded::class))->toBeTrue();
    expect(class_exists(App\Domain\Account\Events\MoneySubtracted::class))->toBeTrue();
    expect(class_exists(App\Domain\Account\Events\MoneyTransferred::class))->toBeTrue();
    expect(class_exists(App\Domain\Asset\Events\AssetTransactionCreated::class))->toBeTrue();
    expect(class_exists(App\Domain\Asset\Events\AssetTransferInitiated::class))->toBeTrue();
    expect(class_exists(App\Domain\Asset\Events\AssetTransferCompleted::class))->toBeTrue();
    expect(class_exists(App\Domain\Asset\Events\AssetTransferFailed::class))->toBeTrue();
});

// Test aggregates and projectors
it('can test existing aggregate and projector classes', function () {
    expect(class_exists(App\Domain\Account\Aggregates\LedgerAggregate::class))->toBeTrue();
    expect(class_exists(App\Domain\Account\Aggregates\TransactionAggregate::class))->toBeTrue();
    expect(class_exists(App\Domain\Account\Aggregates\TransferAggregate::class))->toBeTrue();
    expect(class_exists(App\Domain\Asset\Aggregates\AssetTransactionAggregate::class))->toBeTrue();
    expect(class_exists(App\Domain\Asset\Aggregates\AssetTransferAggregate::class))->toBeTrue();

    expect(class_exists(App\Domain\Account\Projectors\AccountProjector::class))->toBeTrue();
    expect(class_exists(App\Domain\Account\Projectors\TurnoverProjector::class))->toBeTrue();
    expect(class_exists(App\Domain\Asset\Projectors\AssetTransactionProjector::class))->toBeTrue();
    expect(class_exists(App\Domain\Asset\Projectors\AssetTransferProjector::class))->toBeTrue();
});
