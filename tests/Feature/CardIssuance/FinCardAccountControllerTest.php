<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();

    $this->app->instance(FinCardClient::class, new FinCardClient(
        baseUrl: 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual',
        tenantId: 't',
        orgId: 'o',
        userId: 'u',
        username: 'x',
        password: 'y',
    ));

    Http::fake([
        'sandbox.finhub.cloud/api/v2.1/admin/*' => Http::response([
            'success' => true, 'data' => ['accessToken' => 'jwt', 'expiresIn' => 3600],
        ]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/wallet/v2/coins' => Http::response([
            'success' => true, 'data' => [['coinKey' => 'USDT_TRC20', 'chain' => 'TRON']],
        ]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/account/create' => Http::response([
            'success' => true, 'data' => ['accountId' => 'acc-new'],
        ]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/wallet/v2/create' => Http::response([
            'success' => true, 'data' => ['address' => 'TXyz', 'chain' => 'TRON', 'confirmations' => 38],
        ]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/account/single/query' => Http::response([
            'success' => true, 'data' => ['balance' => '12.34'],
        ]),
    ]);

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['read', 'write', 'delete']);
});

it('reports not-provisioned when the user has no FinCard account', function () {
    $this->getJson('/api/v1/cards/account')
        ->assertOk()
        ->assertJsonPath('data.provisioned', false)
        ->assertJsonPath('data.balance_cents', 0);
});

it('returns the account with a reconciled balance', function () {
    $account = new FinCardAccount();
    $account->user_id = (string) $this->user->id;
    $account->fincard_account_id = 'acc-1';
    $account->currency = 'USD';
    $account->balance_cents = 100;
    $account->status = 'active';
    $account->save();

    $this->getJson('/api/v1/cards/account')
        ->assertOk()
        ->assertJsonPath('data.provisioned', true)
        ->assertJsonPath('data.account_id', 'acc-1')
        ->assertJsonPath('data.balance_cents', 1234); // reconciled from FinCard
});

it('lists supported deposit coins', function () {
    $this->getJson('/api/v1/cards/account/coins')
        ->assertOk()
        ->assertJsonPath('data.coins.0.coinKey', 'USDT_TRC20');
});

it('creates a deposit address', function () {
    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/account/deposit-address', ['coin_key' => 'USDT_TRC20'])
        ->assertCreated()
        ->assertJsonPath('data.coin_key', 'USDT_TRC20')
        ->assertJsonPath('data.address', 'TXyz')
        ->assertJsonPath('data.chain', 'TRON');

    expect(App\Domain\CardIssuance\Models\FinCardDepositAddress::where('user_id', $this->user->id)->where('coin_key', 'USDT_TRC20')->exists())->toBeTrue();
});

it('validates the coin_key format', function () {
    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/account/deposit-address', ['coin_key' => 'not a coin!'])
        ->assertStatus(422);
});
