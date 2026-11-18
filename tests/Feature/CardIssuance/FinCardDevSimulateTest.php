<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Events\Broadcast\FinCardAccountFunded;
use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // The simulate path never calls FinCard, but the service constructor needs a
    // client — bind a dummy so the container resolves it without live creds.
    $this->app->instance(FinCardClient::class, new FinCardClient(
        baseUrl: 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual',
        tenantId: 't',
        orgId: 'o',
        userId: 'u',
        username: 'x',
        password: 'y',
    ));

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['read', 'write', 'delete']);
});

it('simulates a deposit and credits a fresh account when enabled', function () {
    config(['cardissuance.issuers.fincard.dev_simulate_enabled' => true]);
    Event::fake([FinCardAccountFunded::class]);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/dev/simulate-deposit', ['amount_cents' => 25000, 'coin_key' => 'USDT_TRC20'])
        ->assertOk()
        ->assertJsonPath('data.provisioned', true)
        ->assertJsonPath('data.balance_cents', 25000);

    expect((int) FinCardAccount::query()->where('user_id', $this->user->id)->value('balance_cents'))->toBe(25000);
    Event::assertDispatched(FinCardAccountFunded::class);
});

it('accumulates onto an existing account balance', function () {
    config(['cardissuance.issuers.fincard.dev_simulate_enabled' => true]);

    $account = new FinCardAccount();
    $account->user_id = (string) $this->user->id;
    $account->fincard_account_id = 'acc-x';
    $account->currency = 'USD';
    $account->balance_cents = 10000;
    $account->status = 'active';
    $account->save();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/dev/simulate-deposit', ['amount_cents' => 5000])
        ->assertOk()
        ->assertJsonPath('data.account_id', 'acc-x')
        ->assertJsonPath('data.balance_cents', 15000);
});

it('404s when the dev flag is disabled', function () {
    config(['cardissuance.issuers.fincard.dev_simulate_enabled' => false]);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/dev/simulate-deposit', ['amount_cents' => 5000])
        ->assertNotFound();
});

it('validates the amount', function () {
    config(['cardissuance.issuers.fincard.dev_simulate_enabled' => true]);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/dev/simulate-deposit', ['amount_cents' => 0])
        ->assertStatus(422);
});
