<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Banking\Models\BankAccountModel;
use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\Compliance\Models\ComplianceCase;
use App\Domain\Custodian\Connectors\DemoBankConnector;
use App\Domain\Custodian\Contracts\ICustodianConnector;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Services\CustodianAccountService;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\Exchange\Projections\Order;
use App\Domain\Exchange\Projections\Trade;
use App\Domain\Fraud\Models\FraudCase;
use App\Domain\Lending\Models\LoanApplication;
use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Contracts\PayseraDepositServiceInterface;
use App\Domain\Payment\Models\PaymentTransaction;
use App\Domain\Payment\Services\DemoPayseraDepositService;
use App\Domain\Payment\Services\PayseraDepositService;
use App\Domain\SMS\Models\SmsMessage;
use App\Domain\Wallet\Models\MultiSigWallet;
use App\Domain\Wallet\Models\MultiSigWalletSigner;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Providers\DemoServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

/*
| Authorization rules on GraphQL account/custodian/wallet operations, the
| owner scoping of GraphQL reads, staff-only compliance/fraud/SMS records,
| Paysera response verification, custodian account links, the demo bank
| connector and the trading default.
*/

beforeEach(function (): void {
    Cache::forget('domain_manager_disabled');
    config([
        'modules.disabled'              => [],
        'ledger.direct_funding_enabled' => true,
        'trading.enabled'               => true,
    ]);
});

/**
 * @param  array<string, mixed>  $variables
 * @return array<string, mixed>
 */
function hardeningGraphql(PHPUnit\Framework\TestCase $test, User $user, string $query, array $variables = []): array
{
    $response = $test->actingAs($user, 'sanctum')->postJson('/graphql', [
        'query'     => $query,
        'variables' => $variables,
    ]);
    $response->assertOk();

    /** @var array<string, mixed> $json */
    $json = $response->json();

    return $json;
}

/**
 * @param  array<string, mixed>  $json
 */
function hardeningGraphqlError(array $json): ?string
{
    /** @var array<int, array{message?: string}> $errors */
    $errors = $json['errors'] ?? [];

    return $errors[0]['message'] ?? null;
}

/**
 * Bind a Mockery double of the given class in the container.
 *
 * @param  class-string  $abstract
 * @param  Closure(MockInterface): void  $expectations
 */
function hardeningMock(string $abstract, Closure $expectations): MockInterface
{
    $mock = Mockery::mock($abstract);
    $expectations($mock);
    app()->instance($abstract, $mock);

    return $mock;
}

function hardeningWallet(User $owner): MultiSigWallet
{
    /** @var MultiSigWallet $wallet */
    $wallet = MultiSigWallet::query()->forceCreate([
        'id'                  => (string) Str::uuid(),
        'user_id'             => $owner->id,
        'name'                => 'Treasury',
        'chain'               => 'ethereum',
        'required_signatures' => 1,
        'total_signers'       => 2,
        'status'              => 'active',
    ]);

    return $wallet;
}

/**
 * Store a pending Paysera order as PayseraDepositService::initiateDeposit does.
 *
 * @return array{0: User, 1: Account, 2: string}
 */
function hardeningPayseraOrder(int $amount = 2500, string $currency = 'EUR'): array
{
    $user = User::factory()->withPersonalTeam()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    $orderId = 'PAY-' . strtoupper(Str::random(20));

    Cache::put("paysera_order:{$orderId}", [
        'order_id'     => $orderId,
        'account_uuid' => $account->uuid,
        'amount'       => $amount,
        'currency'     => $currency,
        'user_id'      => $user->id,
        'status'       => 'pending',
        'created_at'   => now()->toIso8601String(),
        'updated_at'   => now()->toIso8601String(),
    ], now()->addHour());

    return [$user, $account, $orderId];
}

/**
 * Paysera's signed response fields for an order.
 *
 * @param  array<string, string|int>  $overrides
 * @return array{data: string, ss1: string}
 */
function hardeningPayseraResponse(string $orderId, array $overrides = [], string $password = 'test-sign-password'): array
{
    $data = PayseraDepositService::encodePayseraData(http_build_query(array_merge([
        'projectid' => '12345',
        'orderid'   => $orderId,
        'amount'    => 2500,
        'currency'  => 'EUR',
        'status'    => '1',
        'requestid' => 'REQ-' . $orderId,
        'payment'   => 'hanzabankas',
        'test'      => '0',
    ], $overrides)));

    return ['data' => $data, 'ss1' => md5($data . $password)];
}

/**
 * @param  array<string, string>  $params
 */
function hardeningPayseraCallbackUrl(array $params): string
{
    return '/wallet/deposit/paysera/callback?' . http_build_query($params);
}

/**
 * Evaluate a config file with the given environment variables (null = unset)
 * and restore the process environment afterwards.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function hardeningConfigUnderEnv(string $file, array $env): array
{
    $saved = [];
    foreach ($env as $key => $value) {
        $saved[$key] = [
            'has_server' => array_key_exists($key, $_SERVER),
            'server'     => $_SERVER[$key] ?? null,
            'has_env'    => array_key_exists($key, $_ENV),
            'env'        => $_ENV[$key] ?? null,
            'getenv'     => getenv($key),
        ];

        if ($value === null) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        } else {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    try {
        /** @var array<string, mixed> $config */
        $config = require base_path("config/{$file}.php");

        return $config;
    } finally {
        foreach ($saved as $key => $previous) {
            if ($previous['has_server']) {
                $_SERVER[$key] = $previous['server'];
            } else {
                unset($_SERVER[$key]);
            }
            if ($previous['has_env']) {
                $_ENV[$key] = $previous['env'];
            } else {
                unset($_ENV[$key]);
            }
            if ($previous['getenv'] === false) {
                putenv($key);
            } else {
                putenv("{$key}={$previous['getenv']}");
            }
        }
    }
}

describe('GraphQL freezeAccount / unfreezeAccount', function (): void {
    it('refuses to freeze or unfreeze an account of another user', function (): void {
        $owner = User::factory()->create();
        $open = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => false]);
        $frozen = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => true]);
        $attacker = User::factory()->create();

        $json = hardeningGraphql($this, $attacker, '
            mutation ($input: FreezeAccountInput!) { freezeAccount(input: $input) { id frozen } }
        ', ['input' => ['id' => (string) $open->id, 'reason' => 'x']]);

        expect(hardeningGraphqlError($json))->toBe('You are not authorized to freeze this account.');
        expect((bool) $open->fresh()?->frozen)->toBeFalse();

        $json = hardeningGraphql($this, $attacker, '
            mutation ($id: ID!) { unfreezeAccount(id: $id) { id frozen } }
        ', ['id' => (string) $frozen->id]);

        expect(hardeningGraphqlError($json))->toBe('You are not authorized to unfreeze this account.');
        expect((bool) $frozen->fresh()?->frozen)->toBeTrue();
    });

    it('lets the owner freeze and unfreeze their own account', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => false]);

        $json = hardeningGraphql($this, $owner, '
            mutation ($input: FreezeAccountInput!) { freezeAccount(input: $input) { id frozen } }
        ', ['input' => ['id' => (string) $account->id, 'reason' => 'lost card']]);

        expect($json)->not->toHaveKey('errors');
        expect($json['data']['freezeAccount']['frozen'] ?? null)->toBeTrue();

        $json = hardeningGraphql($this, $owner, '
            mutation ($id: ID!) { unfreezeAccount(id: $id) { id frozen } }
        ', ['id' => (string) $account->id]);

        expect($json)->not->toHaveKey('errors');
        expect($json['data']['unfreezeAccount']['frozen'] ?? null)->toBeFalse();
    });

    it('lets an admin role freeze and unfreeze any account', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => false]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $json = hardeningGraphql($this, $admin, '
            mutation ($input: FreezeAccountInput!) { freezeAccount(input: $input) { id frozen } }
        ', ['input' => ['id' => (string) $account->id, 'reason' => 'compliance']]);

        expect($json)->not->toHaveKey('errors');
        expect((bool) $account->fresh()?->frozen)->toBeTrue();

        $json = hardeningGraphql($this, $admin, '
            mutation ($id: ID!) { unfreezeAccount(id: $id) { id frozen } }
        ', ['id' => (string) $account->id]);

        expect($json)->not->toHaveKey('errors');
        expect((bool) $account->fresh()?->frozen)->toBeFalse();
    });
});

describe('GraphQL initiateCustodianTransfer', function (): void {
    it('refuses a source account the caller does not own and never reaches the custodian', function (): void {
        $victim = User::factory()->create();
        $from = Account::factory()->create(['user_uuid' => $victim->uuid]);
        $to = Account::factory()->create(['user_uuid' => $victim->uuid]);
        $attacker = User::factory()->create();

        hardeningMock(CustodianAccountService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('initiateTransfer');
        });

        $json = hardeningGraphql($this, $attacker, '
            mutation ($i: InitiateCustodianTransferInput!) { initiateCustodianTransfer(input: $i) { id } }
        ', ['i' => [
            'from_account_uuid' => $from->uuid,
            'to_account_uuid'   => $to->uuid,
            'amount'            => 50.0,
            'asset_code'        => 'EUR',
        ]]);

        expect(hardeningGraphqlError($json))->toBe('You are not authorized to use this account.');
    });

    it('lets the owner of the source account through to the custodian service', function (): void {
        $owner = User::factory()->create();
        $from = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $to = Account::factory()->create(['user_uuid' => $owner->uuid]);
        CustodianAccount::create(['account_uuid' => $from->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'mock-account-1', 'status' => 'active', 'is_primary' => true]);
        CustodianAccount::create(['account_uuid' => $to->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'mock-account-2', 'status' => 'active', 'is_primary' => false]);

        hardeningMock(CustodianAccountService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('initiateTransfer')->once()->andReturn('missing-transfer-id');
        });

        $json = hardeningGraphql($this, $owner, '
            mutation ($i: InitiateCustodianTransferInput!) { initiateCustodianTransfer(input: $i) { id } }
        ', ['i' => [
            'from_account_uuid' => $from->uuid,
            'to_account_uuid'   => $to->uuid,
            'amount'            => 50.0,
            'asset_code'        => 'EUR',
        ]]);

        expect(hardeningGraphqlError($json))->not->toBe('You are not authorized to use this account.');
    });
});

/**
 * @param  array<string, mixed>  $input
 * @return array<string, mixed>
 */
function hardeningLinkCustodian(PHPUnit\Framework\TestCase $test, User $user, array $input): array
{
    return hardeningGraphql($test, $user, '
        mutation ($i: LinkCustodianAccountInput!) { linkCustodianAccount(input: $i) { id account_uuid custodian_name custodian_account_id status } }
    ', ['i' => $input]);
}

describe('GraphQL custodian account links', function (): void {
    it('refuses to link another user\'s internal account', function (): void {
        $victim = User::factory()->create();
        $victimAccount = Account::factory()->create(['user_uuid' => $victim->uuid]);
        $attacker = User::factory()->create();

        $json = hardeningLinkCustodian($this, $attacker, [
            'account_uuid'         => $victimAccount->uuid,
            'custodian_name'       => 'mock',
            'custodian_account_id' => 'mock-account-2',
        ]);

        expect(hardeningGraphqlError($json))->toBe('You are not authorized to use this account.');
        expect(CustodianAccount::query()->where('account_uuid', $victimAccount->uuid)->exists())->toBeFalse();
    });

    it('leaves links to a real custodian to operators, even for the account owner', function (): void {
        /** @var ICustodianConnector&MockInterface $connector */
        $connector = Mockery::mock(ICustodianConnector::class);
        $connector->shouldNotReceive('validateAccount');
        app(CustodianRegistry::class)->register('realbank', $connector);

        $owner = User::factory()->create();
        $own = Account::factory()->create(['user_uuid' => $owner->uuid]);

        $json = hardeningLinkCustodian($this, $owner, [
            'account_uuid'         => $own->uuid,
            'custodian_name'       => 'realbank',
            'custodian_account_id' => 'someone-elses-bank-account',
        ]);

        expect(hardeningGraphqlError($json))->toBe('Custodian accounts are linked by an operator.');
        expect(CustodianAccount::query()->where('account_uuid', $own->uuid)->exists())->toBeFalse();
    });

    it('lets the owner link a demo custodian only while the demo ledger is on', function (): void {
        $owner = User::factory()->create();
        $own = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $input = ['account_uuid' => $own->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'mock-account-1'];

        config(['ledger.direct_funding_enabled' => false]);
        $json = hardeningLinkCustodian($this, $owner, $input);
        expect(hardeningGraphqlError($json))->toBe('Custodian accounts are linked by an operator.');
        expect(CustodianAccount::query()->where('account_uuid', $own->uuid)->exists())->toBeFalse();

        config(['ledger.direct_funding_enabled' => true]);
        $json = hardeningLinkCustodian($this, $owner, $input);
        expect($json)->not->toHaveKey('errors');
        expect($json['data']['linkCustodianAccount']['account_uuid'] ?? null)->toBe($own->uuid);
    });

    it('lets an operator link any internal account', function (): void {
        config(['ledger.direct_funding_enabled' => false]);
        $customer = User::factory()->create();
        $customerAccount = Account::factory()->create(['user_uuid' => $customer->uuid]);
        $operator = User::factory()->create();
        $operator->assignRole('admin');

        $json = hardeningLinkCustodian($this, $operator, [
            'account_uuid'         => $customerAccount->uuid,
            'custodian_name'       => 'mock',
            'custodian_account_id' => 'mock-account-1',
        ]);

        expect($json)->not->toHaveKey('errors');
        expect(CustodianAccount::query()->where('account_uuid', $customerAccount->uuid)->where('custodian_account_id', 'mock-account-1')->exists())->toBeTrue();
    });

    it('lets only the owner or an operator unlink or sync a link', function (): void {
        $owner = User::factory()->create();
        $ownerAccount = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $link = CustodianAccount::create(['account_uuid' => $ownerAccount->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'mock-account-1', 'status' => 'active', 'is_primary' => true]);
        $attacker = User::factory()->create();
        $operator = User::factory()->create();
        $operator->assignRole('admin');

        hardeningMock(CustodianAccountService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('syncAccountStatus')->twice();
            $mock->shouldReceive('unlinkAccount')->once();
        });

        $json = hardeningGraphql($this, $attacker, 'mutation ($id: ID!) { unlinkCustodianAccount(id: $id) }', ['id' => (string) $link->id]);
        expect(hardeningGraphqlError($json))->toBe('You are not authorized to manage this custodian account.');

        $json = hardeningGraphql($this, $attacker, 'mutation ($id: ID!) { syncCustodianAccount(id: $id) { id } }', ['id' => (string) $link->id]);
        expect(hardeningGraphqlError($json))->toBe('You are not authorized to manage this custodian account.');

        $json = hardeningGraphql($this, $owner, 'mutation ($id: ID!) { syncCustodianAccount(id: $id) { id } }', ['id' => (string) $link->id]);
        expect($json)->not->toHaveKey('errors');

        $json = hardeningGraphql($this, $operator, 'mutation ($id: ID!) { syncCustodianAccount(id: $id) { id } }', ['id' => (string) $link->id]);
        expect($json)->not->toHaveKey('errors');

        $json = hardeningGraphql($this, $owner, 'mutation ($id: ID!) { unlinkCustodianAccount(id: $id) }', ['id' => (string) $link->id]);
        expect($json)->not->toHaveKey('errors');
        expect($json['data']['unlinkCustodianAccount'] ?? null)->toBeTrue();
    });
});

describe('GraphQL transferFunds', function (): void {
    it('refuses a wallet the caller neither owns nor signs for', function (): void {
        $victim = User::factory()->create();
        $wallet = hardeningWallet($victim);
        $attacker = User::factory()->create();

        hardeningMock(WalletService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('transfer');
        });

        $json = hardeningGraphql($this, $attacker, '
            mutation ($i: TransferFundsInput!) { transferFunds(input: $i) { id } }
        ', ['i' => [
            'from_wallet_id' => $wallet->id,
            'to_address'     => '0xabc',
            'amount'         => 1.5,
            'chain'          => 'USD',
        ]]);

        expect(hardeningGraphqlError($json))->toBe('You are not authorized to use this wallet.');
    });

    it('lets the owner and an active signer use the wallet', function (): void {
        $owner = User::factory()->create();
        $wallet = hardeningWallet($owner);
        $signer = User::factory()->create();
        MultiSigWalletSigner::create([
            'multi_sig_wallet_id' => $wallet->id,
            'user_id'             => $signer->id,
            'signer_type'         => 'internal',
            'public_key'          => '0xpub',
            'signer_order'        => 1,
            'is_active'           => true,
        ]);

        hardeningMock(WalletService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('transfer')->twice();
        });

        foreach ([$owner, $signer] as $caller) {
            $json = hardeningGraphql($this, $caller, '
                mutation ($i: TransferFundsInput!) { transferFunds(input: $i) { id } }
            ', ['i' => [
                'from_wallet_id' => $wallet->id,
                'to_address'     => '0xabc',
                'amount'         => 1.5,
                'chain'          => 'USD',
            ]]);

            expect($json)->not->toHaveKey('errors');
            expect($json['data']['transferFunds']['id'] ?? null)->toBe($wallet->id);
        }
    });
});

describe('GraphQL reads are limited to the caller', function (): void {
    it('returns accounts of the caller only', function (): void {
        $victim = User::factory()->create();
        $victimAccount = Account::factory()->create(['user_uuid' => $victim->uuid, 'name' => 'VICTIM-SAVINGS']);
        $caller = User::factory()->create();
        $own = Account::factory()->create(['user_uuid' => $caller->uuid, 'name' => 'OWN-SAVINGS']);

        $json = hardeningGraphql($this, $caller, 'query ($id: ID!) { account(id: $id) { uuid name } }', ['id' => $victimAccount->id]);
        expect($json['data']['account'])->toBeNull();

        $json = hardeningGraphql($this, $caller, 'query ($u: String!) { accountByUuid(uuid: $u) { uuid name } }', ['u' => $victimAccount->uuid]);
        expect($json['data']['accountByUuid'])->toBeNull();

        $json = hardeningGraphql($this, $caller, 'query ($id: ID!) { account(id: $id) { uuid name } }', ['id' => $own->id]);
        expect($json['data']['account']['name'] ?? null)->toBe('OWN-SAVINGS');

        $json = hardeningGraphql($this, $caller, '{ accounts(first: 20) { data { name } paginatorInfo { total } } }');
        expect(array_column($json['data']['accounts']['data'], 'name'))->toBe(['OWN-SAVINGS']);
        expect($json['data']['accounts']['paginatorInfo']['total'])->toBe(1);
    });

    it('returns payments on the caller\'s accounts only', function (): void {
        $victim = User::factory()->create();
        $victimAccount = Account::factory()->create(['user_uuid' => $victim->uuid]);
        $caller = User::factory()->create();
        $own = Account::factory()->create(['user_uuid' => $caller->uuid]);

        $payment = fn (Account $account, string $reference): PaymentTransaction => PaymentTransaction::create([
            'aggregate_uuid' => (string) Str::uuid(),
            'account_uuid'   => $account->uuid,
            'type'           => 'deposit',
            'status'         => 'completed',
            'amount'         => 1000,
            'currency'       => 'USD',
            'reference'      => $reference,
            'initiated_at'   => now(),
        ]);
        $victimPayment = $payment($victimAccount, 'VICTIM-REF');
        $payment($own, 'OWN-REF');

        $json = hardeningGraphql($this, $caller, 'query ($id: ID!) { payment(id: $id) { reference } }', ['id' => $victimPayment->id]);
        expect($json['data']['payment'])->toBeNull();

        $json = hardeningGraphql($this, $caller, '{ payments(first: 20) { data { reference } } }');
        expect(array_column($json['data']['payments']['data'], 'reference'))->toBe(['OWN-REF']);
    });

    it('returns wallets the caller owns or signs for only', function (): void {
        $victim = User::factory()->create();
        $victimWallet = hardeningWallet($victim);
        $caller = User::factory()->create();
        $ownWallet = hardeningWallet($caller);
        $sharedOwner = User::factory()->create();
        $sharedWallet = hardeningWallet($sharedOwner);
        MultiSigWalletSigner::create([
            'multi_sig_wallet_id' => $sharedWallet->id,
            'user_id'             => $caller->id,
            'signer_type'         => 'internal',
            'public_key'          => '0xpub',
            'signer_order'        => 1,
            'is_active'           => true,
        ]);

        $json = hardeningGraphql($this, $caller, 'query ($id: ID!) { wallet(id: $id) { id } }', ['id' => $victimWallet->id]);
        expect($json['data']['wallet'])->toBeNull();

        $json = hardeningGraphql($this, $caller, 'query ($id: ID!) { wallet(id: $id) { id } }', ['id' => $sharedWallet->id]);
        expect($json['data']['wallet']['id'] ?? null)->toBe($sharedWallet->id);

        $json = hardeningGraphql($this, $caller, '{ wallets(first: 20) { data { id } } }');
        $ids = array_column($json['data']['wallets']['data'], 'id');
        sort($ids);
        $expected = [$ownWallet->id, $sharedWallet->id];
        sort($expected);
        expect($ids)->toBe($expected);
    });

    it('returns exchange orders and trades of the caller\'s accounts only', function (): void {
        $victim = User::factory()->create();
        $victimAccount = Account::factory()->create(['user_uuid' => $victim->uuid]);
        $caller = User::factory()->create();
        $own = Account::factory()->create(['user_uuid' => $caller->uuid]);

        $order = fn (Account $account, string $orderId): Order => Order::create([
            'order_id'       => $orderId,
            'account_id'     => (string) $account->id,
            'type'           => 'buy',
            'order_type'     => 'limit',
            'base_currency'  => 'BTC',
            'quote_currency' => 'USD',
            'amount'         => 1,
            'price'          => 50000,
            'status'         => 'open',
        ]);
        $victimOrder = $order($victimAccount, 'b1b2c3d4-0000-4000-8000-000000000001');
        $order($own, 'b1b2c3d4-0000-4000-8000-000000000002');

        $trade = fn (string $tradeId, Account $buyer, Account $seller): Trade => Trade::create([
            'trade_id'          => $tradeId,
            'buy_order_id'      => (string) Str::uuid(),
            'sell_order_id'     => (string) Str::uuid(),
            'buyer_account_id'  => (string) $buyer->id,
            'seller_account_id' => (string) $seller->id,
            'base_currency'     => 'BTC',
            'quote_currency'    => 'USD',
            'price'             => 50000,
            'amount'            => 1,
            'value'             => 50000,
            'maker_fee'         => 0,
            'taker_fee'         => 0,
            'maker_side'        => 'buy',
        ]);
        $other = Account::factory()->create(['user_uuid' => User::factory()->create()->uuid]);
        $trade('b1b2c3d4-0000-4000-8000-000000000101', $victimAccount, $other);
        $trade('b1b2c3d4-0000-4000-8000-000000000102', $own, $victimAccount);
        $trade('b1b2c3d4-0000-4000-8000-000000000103', $other, $own);

        $json = hardeningGraphql($this, $caller, 'query ($id: ID!) { order(id: $id) { order_id } }', ['id' => $victimOrder->id]);
        expect($json['data']['order'])->toBeNull();

        $json = hardeningGraphql($this, $caller, '{ orders(first: 20) { data { order_id } } }');
        expect(array_column($json['data']['orders']['data'], 'order_id'))->toBe(['b1b2c3d4-0000-4000-8000-000000000002']);

        $json = hardeningGraphql($this, $caller, '{ trades(first: 20) { data { trade_id } } }');
        $tradeIds = array_column($json['data']['trades']['data'], 'trade_id');
        sort($tradeIds);
        expect($tradeIds)->toBe(['b1b2c3d4-0000-4000-8000-000000000102', 'b1b2c3d4-0000-4000-8000-000000000103']);
    });

    it('returns custodian links of the caller\'s accounts only and guards custodianBalance', function (): void {
        $victim = User::factory()->create();
        $victimAccount = Account::factory()->create(['user_uuid' => $victim->uuid]);
        $caller = User::factory()->create();
        $own = Account::factory()->create(['user_uuid' => $caller->uuid]);
        $victimLink = CustodianAccount::create(['account_uuid' => $victimAccount->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'VICTIM-CUST', 'status' => 'active', 'is_primary' => true]);
        $ownLink = CustodianAccount::create(['account_uuid' => $own->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'OWN-CUST', 'status' => 'active', 'is_primary' => true]);

        $json = hardeningGraphql($this, $caller, 'query ($id: ID!) { custodianAccount(id: $id) { id } }', ['id' => $victimLink->id]);
        expect($json['data']['custodianAccount'])->toBeNull();

        $json = hardeningGraphql($this, $caller, '{ custodianAccounts(first: 20) { data { id } } }');
        expect(array_map('strval', array_column($json['data']['custodianAccounts']['data'], 'id')))->toBe([(string) $ownLink->id]);

        $json = hardeningGraphql($this, $caller, 'query ($id: ID!) { custodianBalance(account_id: $id) }', ['id' => $victimLink->id]);
        expect(hardeningGraphqlError($json))->toBe('You are not authorized to view this account.');

        $json = hardeningGraphql($this, $caller, 'query ($id: ID!) { custodianBalance(account_id: $id) }', ['id' => $ownLink->id]);
        expect($json)->not->toHaveKey('errors');
    });

    it('returns loan applications of the caller only, whichever borrower id form was stored', function (): void {
        $victim = User::factory()->create();
        $caller = User::factory()->create();

        $application = fn (string $borrowerId, string $purpose): LoanApplication => LoanApplication::create([
            'borrower_id'      => $borrowerId,
            'requested_amount' => 1000,
            'term_months'      => 12,
            'purpose'          => $purpose,
            'status'           => 'submitted',
            'borrower_info'    => ['name' => 'n'],
            'submitted_at'     => now(),
        ]);
        $victimApplication = $application((string) $victim->id, 'VICTIM');
        $application((string) $caller->id, 'OWN-ID');
        $application((string) $caller->uuid, 'OWN-UUID');

        $json = hardeningGraphql($this, $caller, 'query ($id: ID!) { loanApplication(id: $id) { purpose } }', ['id' => $victimApplication->id]);
        expect($json['data']['loanApplication'])->toBeNull();

        $json = hardeningGraphql($this, $caller, '{ loanApplications(first: 20) { data { purpose } } }');
        $purposes = array_column($json['data']['loanApplications']['data'], 'purpose');
        sort($purposes);
        expect($purposes)->toBe(['OWN-ID', 'OWN-UUID']);
    });

    it('returns bank accounts of the caller only and refuses another user\'s aggregated balance', function (): void {
        $victim = User::factory()->create();
        $caller = User::factory()->create();
        $bankAccount = fn (User $user): BankAccountModel => BankAccountModel::query()->create([
            'id'             => (string) Str::uuid(),
            'user_uuid'      => $user->uuid,
            'bank_code'      => 'REVOLUT',
            'external_id'    => (string) Str::uuid(),
            'account_number' => encrypt('12345678'),
            'iban'           => encrypt('GB00REVO00000012345678'),
            'swift'          => 'REVOGB21',
            'currency'       => 'EUR',
            'account_type'   => 'checking',
            'status'         => 'active',
            'metadata'       => [],
        ]);
        $bankAccount($victim);
        $own = $bankAccount($caller);

        $json = hardeningGraphql($this, $caller, '{ bankAccounts(first: 20) { data { id } } }');
        expect(array_map('strval', array_column($json['data']['bankAccounts']['data'], 'id')))->toBe([(string) $own->id]);

        $json = hardeningGraphql($this, $caller, 'query ($u: String!) { aggregatedBalance(user_uuid: $u) { currency } }', ['u' => $victim->uuid]);
        expect(hardeningGraphqlError($json))->toBe('You are not authorized to view these balances.');
    });

    it('scopes every GraphQL query on a user-owned model to the caller', function (string $file, string $field, string $method): void {
        $schema = (string) file_get_contents(base_path("graphql/{$file}"));

        expect(preg_match('/extend type Query[^{\n]*\{(.*?)\n\}/s', $schema, $query))->toBe(1);

        // The field's definition runs up to the next field (4-space indent).
        expect(preg_match('/\n    ' . preg_quote($field, '/') . '\s*[(:](.*?)(?=\n    [a-zA-Z_]+\s*[(:]|$)/s', $query[1] ?? '', $definition))->toBe(1);

        expect($definition[1] ?? '')->toContain('@builder(method: "App\\\\GraphQL\\\\Builders\\\\OwnedByUser@' . $method . '")');
    })->with([
        ['account.graphql', 'account', 'userUuid'],
        ['account.graphql', 'accounts', 'userUuid'],
        ['account.graphql', 'accountByUuid', 'userUuid'],
        ['payment.graphql', 'payment', 'accountUuid'],
        ['payment.graphql', 'payments', 'accountUuid'],
        ['wallet.graphql', 'wallet', 'wallet'],
        ['wallet.graphql', 'wallets', 'wallet'],
        ['banking.graphql', 'bankAccount', 'userUuid'],
        ['banking.graphql', 'bankAccounts', 'userUuid'],
        ['banking.graphql', 'bankConnection', 'userUuid'],
        ['banking.graphql', 'bankConnections', 'userUuid'],
        ['batch.graphql', 'batchJob', 'userUuid'],
        ['batch.graphql', 'batchJobs', 'userUuid'],
        ['cgo.graphql', 'investment', 'userId'],
        ['cgo.graphql', 'investments', 'userId'],
        ['compliance.graphql', 'kycVerification', 'userId'],
        ['compliance.graphql', 'kycVerifications', 'userId'],
        ['crosschain.graphql', 'bridgeTransaction', 'userId'],
        ['crosschain.graphql', 'bridgeTransactions', 'userId'],
        ['custodian.graphql', 'custodianAccount', 'accountUuid'],
        ['custodian.graphql', 'custodianAccounts', 'accountUuid'],
        ['custodian.graphql', 'custodianTransfer', 'custodianTransfer'],
        ['custodian.graphql', 'custodianTransfers', 'custodianTransfer'],
        ['defi.graphql', 'defiPosition', 'userId'],
        ['defi.graphql', 'defiPositions', 'userId'],
        ['exchange.graphql', 'order', 'accountId'],
        ['exchange.graphql', 'orders', 'accountId'],
        ['exchange.graphql', 'trades', 'trade'],
        ['governance.graphql', 'vote', 'userUuid'],
        ['governance.graphql', 'pollVotes', 'userUuid'],
        ['key-management.graphql', 'keyShardRecord', 'userUuid'],
        ['key-management.graphql', 'keyShardRecords', 'userUuid'],
        ['key-management.graphql', 'keyReconstructionLogs', 'userUuid'],
        ['key-management.graphql', 'recoveryBackups', 'userUuid'],
        ['lending.graphql', 'loanApplication', 'borrower'],
        ['lending.graphql', 'loanApplications', 'borrower'],
        ['mobile-payment.graphql', 'paymentIntent', 'userId'],
        ['mobile-payment.graphql', 'paymentIntents', 'userId'],
        ['mobile.graphql', 'mobileDevice', 'userId'],
        ['mobile.graphql', 'mobileDevices', 'userId'],
        ['privacy.graphql', 'delegatedProofJob', 'userId'],
        ['privacy.graphql', 'delegatedProofJobs', 'userId'],
        ['relayer.graphql', 'smartAccount', 'userId'],
        ['relayer.graphql', 'smartAccounts', 'userId'],
        ['relayer.graphql', 'smartAccountByAddress', 'userId'],
        ['trust-cert.graphql', 'certificate', 'userId'],
        ['trust-cert.graphql', 'certificates', 'userId'],
        ['ai.graphql', 'aiLlmUsages', 'userUuid'],
    ]);
});

describe('compliance, fraud and SMS records are staff-only', function (): void {
    it('returns compliance alerts and cases only to compliance staff', function (): void {
        $subject = User::factory()->create();
        $alert = ComplianceAlert::factory()->create(['title' => 'SUBJECT-ALERT', 'entity_type' => 'user', 'entity_id' => (string) $subject->id]);
        $case = ComplianceCase::factory()->create(['title' => 'SUBJECT-CASE']);
        $customer = User::factory()->create();
        $officer = User::factory()->create();
        $officer->assignRole('compliance_officer');

        $queries = [
            ['query ($id: ID!) { complianceAlert(id: $id) { title } }', ['id' => (string) $alert->id], 'complianceAlert'],
            ['query ($id: ID!) { complianceCase(id: $id) { title } }', ['id' => (string) $case->id], 'complianceCase'],
        ];
        foreach ($queries as [$query, $variables, $field]) {
            $json = hardeningGraphql($this, $customer, $query, $variables);
            expect($json['data'][$field])->toBeNull();

            $json = hardeningGraphql($this, $officer, $query, $variables);
            expect($json['data'][$field]['title'] ?? null)->not->toBeNull();
        }

        foreach (['complianceAlerts', 'complianceCases'] as $field) {
            $json = hardeningGraphql($this, $customer, "{ {$field}(first: 20) { data { title } } }");
            expect($json['data'][$field]['data'])->toBe([]);

            $json = hardeningGraphql($this, $officer, "{ {$field}(first: 20) { data { title } } }");
            expect(array_column($json['data'][$field]['data'], 'title'))->toContain($field === 'complianceAlerts' ? 'SUBJECT-ALERT' : 'SUBJECT-CASE');
        }
    });

    it('returns and escalates fraud cases only for compliance staff', function (): void {
        $subject = User::factory()->create();
        $subjectAccount = Account::factory()->create(['user_uuid' => $subject->uuid]);
        $fraudCase = FraudCase::factory()->create(['subject_account_uuid' => $subjectAccount->uuid, 'description' => 'SUBJECT-FRAUD-CASE']);
        $customer = User::factory()->create();
        $analyst = User::factory()->create();
        $analyst->assignRole('risk_manager');

        $json = hardeningGraphql($this, $customer, 'query ($id: ID!) { fraudCase(id: $id) { description } }', ['id' => (string) $fraudCase->id]);
        expect($json['data']['fraudCase'])->toBeNull();
        $json = hardeningGraphql($this, $customer, '{ fraudCases(first: 20) { data { description } } }');
        expect($json['data']['fraudCases']['data'])->toBe([]);

        $json = hardeningGraphql($this, $analyst, 'query ($id: ID!) { fraudCase(id: $id) { description } }', ['id' => (string) $fraudCase->id]);
        expect($json['data']['fraudCase']['description'] ?? null)->toBe('SUBJECT-FRAUD-CASE');
        $json = hardeningGraphql($this, $analyst, '{ fraudCases(first: 20) { data { description } } }');
        expect(array_column($json['data']['fraudCases']['data'], 'description'))->toBe(['SUBJECT-FRAUD-CASE']);

        $escalate = 'mutation ($i: EscalateFraudCaseInput!) { escalateFraudCase(input: $i) { id } }';
        $variables = ['i' => ['id' => (string) $fraudCase->id, 'severity' => 'high']];
        $json = hardeningGraphql($this, $customer, $escalate, $variables);
        expect(hardeningGraphqlError($json))->toBe('You are not authorized to manage fraud cases.');

        $json = hardeningGraphql($this, $analyst, $escalate, $variables);
        expect(hardeningGraphqlError($json))->not->toBe('You are not authorized to manage fraud cases.');
    });

    it('returns SMS send records only to operators', function (): void {
        SmsMessage::query()->create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'vx-msg-1',
            'to'           => '+37060000001',
            'from'         => 'Zelta',
            'message'      => 'code 123456',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '0.050000',
            'country_code' => 'LT',
        ]);
        $customer = User::factory()->create();
        $operator = User::factory()->create();
        $operator->assignRole('admin');

        $query = 'query ($m: String!) { smsStatus(messageId: $m) { to status } }';

        $json = hardeningGraphql($this, $customer, $query, ['m' => 'vx-msg-1']);
        expect($json['data']['smsStatus'])->toBeNull();

        $json = hardeningGraphql($this, $operator, $query, ['m' => 'vx-msg-1']);
        expect($json['data']['smsStatus']['to'] ?? null)->toBe('+37060000001');
    });

    it('refuses the REST compliance alert and case endpoints to non-staff', function (string $path): void {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer, ['read', 'write', 'delete']);
        $this->getJson($path)->assertForbidden();

        $officer = User::factory()->create();
        $officer->assignRole('compliance_officer');
        Sanctum::actingAs($officer, ['read', 'write', 'delete']);
        $this->getJson($path)->assertOk();
    })->with(['/api/compliance/alerts', '/api/compliance/cases']);

    it('limits every GraphQL query on internal records to staff', function (string $file, string $field, string $method): void {
        $schema = (string) file_get_contents(base_path("graphql/{$file}"));

        expect(preg_match('/extend type Query[^{\n]*\{(.*?)\n\}/s', $schema, $query))->toBe(1);
        expect(preg_match('/\n    ' . preg_quote($field, '/') . '\s*[(:](.*?)(?=\n    [a-zA-Z_"]|$)/s', $query[1] ?? '', $definition))->toBe(1);

        expect($definition[1] ?? '')->toContain('@builder(method: "App\\\\GraphQL\\\\Builders\\\\StaffOnly@' . $method . '")');
    })->with([
        ['compliance.graphql', 'complianceAlert', 'compliance'],
        ['compliance.graphql', 'complianceAlerts', 'compliance'],
        ['compliance.graphql', 'complianceCase', 'compliance'],
        ['compliance.graphql', 'complianceCases', 'compliance'],
        ['fraud.graphql', 'fraudCase', 'compliance'],
        ['fraud.graphql', 'fraudCases', 'compliance'],
        ['sms.graphql', 'smsStatus', 'operators'],
    ]);
});

describe('Paysera callback verification', function (): void {
    beforeEach(function (): void {
        config([
            'custodians.connectors.paysera.enabled' => true,
            'services.paysera.project_id'           => '12345',
            'services.paysera.sign_password'        => 'test-sign-password',
        ]);
    });

    it('credits nothing without a valid Paysera signature', function (array $params): void {
        [$user, , $orderId] = hardeningPayseraOrder();

        hardeningMock(PaymentServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('processStripeDeposit');
        });

        $params = array_map(
            fn (string $value): string => str_replace('{order}', $orderId, $value),
            $params
        );
        if (isset($params['data']) && $params['data'] === '{signed}') {
            $params['data'] = hardeningPayseraResponse($orderId)['data'];
        }
        if (isset($params['ss1']) && $params['ss1'] === '{tampered}') {
            $signed = hardeningPayseraResponse($orderId);
            $params['data'] = hardeningPayseraResponse($orderId, ['amount' => 999999])['data'];
            $params['ss1'] = $signed['ss1'];
        }

        $this->actingAs($user)
            ->getJson(hardeningPayseraCallbackUrl($params))
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Payment could not be verified');

        expect(Cache::get("paysera_order:{$orderId}")['status'] ?? null)->toBe('pending');
    })->with([
        'browser-supplied status only' => [['order_id' => '{order}', 'status' => 'completed', 'amount' => '2500', 'currency' => 'EUR']],
        'no parameters but the order'  => [['order_id' => '{order}']],
        'data without ss1'             => [['order_id' => '{order}', 'data' => '{signed}', 'ss1' => '']],
        'ss1 of different data'        => [['order_id' => '{order}', 'ss1' => '{tampered}']],
    ]);

    it('credits nothing when the sign password is not configured', function (): void {
        config(['services.paysera.sign_password' => '']);
        [$user, , $orderId] = hardeningPayseraOrder();

        hardeningMock(PaymentServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('processStripeDeposit');
        });

        $this->actingAs($user)
            ->getJson(hardeningPayseraCallbackUrl(['order_id' => $orderId] + hardeningPayseraResponse($orderId, [], '')))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Payment could not be verified');
    });

    it('credits nothing unless the signed response matches the order and reports status 1', function (array $overrides, string $message): void {
        [$user, , $orderId] = hardeningPayseraOrder();

        hardeningMock(PaymentServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('processStripeDeposit');
        });

        $overrides = array_map(
            fn (string|int $value): string|int => is_string($value) ? str_replace('{order}', $orderId, $value) : $value,
            $overrides
        );

        $this->actingAs($user)
            ->getJson(hardeningPayseraCallbackUrl(['order_id' => $orderId] + hardeningPayseraResponse($orderId, $overrides)))
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', $message);
    })->with([
        'payment not executed'    => [['status' => '0'], 'Payment not completed'],
        'payment accepted only'   => [['status' => '2'], 'Payment not completed'],
        'another order'           => [['orderid' => 'PAY-OTHER'], 'Payment details do not match the order'],
        'another amount'          => [['amount' => 1], 'Payment details do not match the order'],
        'another currency'        => [['currency' => 'USD'], 'Payment details do not match the order'],
        'another Paysera project' => [['projectid' => '99999'], 'Payment details do not match the order'],
    ]);

    it('credits the stored order once on a valid signed response', function (): void {
        [$user, $account, $orderId] = hardeningPayseraOrder(2500);

        hardeningMock(PaymentServiceInterface::class, function (MockInterface $mock) use ($account, $orderId): void {
            $mock->shouldReceive('processStripeDeposit')
                ->once()
                ->with(Mockery::on(fn (array $data): bool => $data['account_uuid'] === $account->uuid
                    && $data['amount'] === 2500
                    && $data['currency'] === 'EUR'
                    && $data['external_reference'] === 'REQ-' . $orderId
                    && $data['payment_method'] === 'paysera'))
                ->andReturn('txn_paysera');
        });

        // The order id may come from the signed response alone.
        $signed = hardeningPayseraResponse($orderId);

        $this->actingAs($user)
            ->getJson(hardeningPayseraCallbackUrl($signed))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($user)
            ->getJson(hardeningPayseraCallbackUrl(['order_id' => $orderId] + $signed))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Deposit already processed');
    });

    it('refuses Paysera test payments in production', function (): void {
        [$user, , $orderId] = hardeningPayseraOrder();

        hardeningMock(PaymentServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('processStripeDeposit');
        });

        $signed = hardeningPayseraResponse($orderId, ['test' => '1']);
        $originalEnv = $this->app['env'];

        try {
            $this->app['env'] = 'production';

            $result = app(PayseraDepositService::class)->handleCallback([
                'order_id'     => $orderId,
                'status'       => 'completed',
                'user_id'      => $user->id,
                'paysera_data' => $signed['data'],
                'paysera_ss1'  => $signed['ss1'],
            ]);
        } finally {
            $this->app['env'] = $originalEnv;
        }

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toBe('Payment details do not match the order');
    });

    it('builds a signed Paysera request pointing back to the callback routes', function (): void {
        $user = User::factory()->withPersonalTeam()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        $response = $this->actingAs($user)
            ->postJson('/wallet/deposit/paysera/initiate', [
                'account_uuid' => $account->uuid,
                'amount'       => 2500,
                'currency'     => 'EUR',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $orderId = (string) $response->json('order_id');
        parse_str((string) parse_url((string) $response->json('redirect_url'), PHP_URL_QUERY), $query);
        $data = is_string($query['data'] ?? null) ? $query['data'] : '';

        expect($data)->not->toBe('')
            ->and($query['sign'] ?? null)->toBe(md5($data . 'test-sign-password'));
        parse_str((string) base64_decode(strtr($data, ['-' => '+', '_' => '/']), true), $request);

        expect($request['orderid'] ?? null)->toBe($orderId)
            ->and($request['projectid'] ?? null)->toBe('12345')
            ->and($request['version'] ?? null)->toBe('1.6')
            ->and($request['accepturl'] ?? null)->toBe(route('wallet.deposit.paysera.callback', ['order_id' => $orderId]))
            // Paysera's server notification goes to the signature-only route.
            ->and($request['callbackurl'] ?? null)->toBe(route('api.webhooks.paysera.deposit'));

        expect(Cache::get("paysera_order:{$orderId}"))->toMatchArray([
            'account_uuid' => $account->uuid,
            'amount'       => 2500,
            'user_id'      => $user->id,
            'status'       => 'pending',
        ]);
    });

    it('does not start a deposit while Paysera is not configured', function (): void {
        config(['services.paysera.sign_password' => '']);
        $user = User::factory()->withPersonalTeam()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        $this->actingAs($user)
            ->postJson('/wallet/deposit/paysera/initiate', [
                'account_uuid' => $account->uuid,
                'amount'       => 2500,
                'currency'     => 'EUR',
            ])
            ->assertStatus(500)
            ->assertJsonPath('success', false);
    });

    it('keeps the simulated flow for the demo service', function (): void {
        $this->app->bind(PayseraDepositServiceInterface::class, DemoPayseraDepositService::class);
        DemoPayseraDepositService::resetDemoOrders();
        $user = User::factory()->withPersonalTeam()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        hardeningMock(PaymentServiceInterface::class, function (MockInterface $mock) use ($account): void {
            $mock->shouldReceive('processStripeDeposit')
                ->once()
                ->with(Mockery::on(fn (array $data): bool => $data['account_uuid'] === $account->uuid && $data['amount'] === 1500))
                ->andReturn('txn_demo');
        });

        $orderId = (string) $this->actingAs($user)
            ->postJson('/wallet/deposit/paysera/initiate', [
                'account_uuid' => $account->uuid,
                'amount'       => 1500,
                'currency'     => 'EUR',
            ])
            ->assertOk()
            ->json('order_id');

        $this->actingAs($user)
            ->getJson(hardeningPayseraCallbackUrl(['order_id' => $orderId, 'status' => 'completed']))
            ->assertOk()
            ->assertJsonPath('success', true);

        DemoPayseraDepositService::resetDemoOrders();
    });
});

describe('REST custodian transfer', function (): void {
    it('requires an active link between the internal account and the custodian account', function (?array $link): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        if ($link !== null) {
            /** @var array<model-property<CustodianAccount>, mixed> $link dataset overrides */
            $custodianAccount = new CustodianAccount();
            $custodianAccount->fill(array_merge([
                'account_uuid'         => $account->uuid,
                'custodian_name'       => 'mock',
                'custodian_account_id' => 'mock-account-1',
                'status'               => 'active',
                'is_primary'           => true,
            ], $link));
            $custodianAccount->save();
        }
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->postJson('/api/custodians/mock/transfer', [
            'internal_account_uuid' => $account->uuid,
            'custodian_account_id'  => 'mock-account-1',
            'asset_code'            => 'USD',
            'amount'                => 10,
            'direction'             => 'deposit',
        ])->assertForbidden()
            ->assertJsonPath('error', 'FORBIDDEN')
            ->assertJsonPath('message', 'Custodian account is not linked to this account');
    })->with([
        'no link'                      => [null],
        'link to another custodian'    => [['custodian_name' => 'paysera']],
        'link to another custodian id' => [['custodian_account_id' => 'mock-account-2']],
        'suspended link'               => [['status' => 'suspended']],
    ]);

    it('refuses a custodian account linked to another user\'s account', function (): void {
        $victim = User::factory()->create();
        $victimAccount = Account::factory()->create(['user_uuid' => $victim->uuid]);
        CustodianAccount::create([
            'account_uuid'         => $victimAccount->uuid,
            'custodian_name'       => 'mock',
            'custodian_account_id' => 'mock-account-1',
            'status'               => 'active',
            'is_primary'           => true,
        ]);
        $caller = User::factory()->create();
        $own = Account::factory()->create(['user_uuid' => $caller->uuid]);
        Sanctum::actingAs($caller, ['read', 'write', 'delete']);

        $this->postJson('/api/custodians/mock/transfer', [
            'internal_account_uuid' => $own->uuid,
            'custodian_account_id'  => 'mock-account-1',
            'asset_code'            => 'USD',
            'amount'                => 10,
            'direction'             => 'deposit',
        ])->assertForbidden()->assertJsonPath('error', 'FORBIDDEN');
    });
});

describe('demo bank connector', function (): void {
    it('is registered in sandbox mode only for local, testing and demo environments', function (string $env, bool $registered): void {
        config(['demo.sandbox.enabled' => true]);
        $registry = new CustodianRegistry();
        $this->app->instance(CustodianRegistry::class, $registry);
        $originalEnv = $this->app['env'];

        try {
            $this->app['env'] = $env;
            // The app has booted, so the provider's booted() callback runs at once.
            (new DemoServiceProvider($this->app))->register();
        } finally {
            $this->app['env'] = $originalEnv;
        }

        expect($registry->has('demo_bank'))->toBe($registered);
        if ($registered) {
            expect($registry->get('demo_bank'))->toBeInstanceOf(DemoBankConnector::class);
        }
    })->with([
        'production' => ['production', false],
        'staging'    => ['staging', false],
        'local'      => ['local', true],
        'testing'    => ['testing', true],
        'demo'       => ['demo', true],
    ]);
});

describe('trading default', function (): void {
    it('is on only under APP_ENV=local, testing or demo when TRADING_ENABLED is unset', function (?string $appEnv, bool $enabled): void {
        $config = hardeningConfigUnderEnv('trading', ['APP_ENV' => $appEnv, 'TRADING_ENABLED' => null]);

        expect($config['enabled'])->toBe($enabled);
    })->with([
        'production' => ['production', false],
        'unset'      => [null, false],
        'demo'       => ['demo', true],
        'local'      => ['local', true],
        'testing'    => ['testing', true],
        'staging'    => ['staging', false],
    ]);

    it('lets an explicit TRADING_ENABLED win', function (): void {
        expect(hardeningConfigUnderEnv('trading', ['APP_ENV' => 'production', 'TRADING_ENABLED' => 'true'])['enabled'])->toBeTrue()
            ->and(hardeningConfigUnderEnv('trading', ['APP_ENV' => 'demo', 'TRADING_ENABLED' => 'false'])['enabled'])->toBeFalse();
    });
});
