<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
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
});

function reconcileTestAccount(int $balanceCents): FinCardAccount
{
    $account = new FinCardAccount();
    $account->user_id = (string) User::factory()->create()->id;
    $account->fincard_account_id = 'acc-1';
    $account->currency = 'USD';
    $account->balance_cents = $balanceCents;
    $account->status = 'active';
    $account->save();

    return $account;
}

it('reconciles a drifted balance when fincard is the issuer', function () {
    config(['cardissuance.default_issuer' => 'fincard']);
    Http::fake([
        'sandbox.finhub.cloud/api/v2.1/admin/*'                              => Http::response(['success' => true, 'data' => ['accessToken' => 'jwt', 'expiresIn' => 3600]]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/account/single/query' => Http::response(['success' => true, 'data' => ['balance' => '12.34']]),
    ]);

    reconcileTestAccount(100); // stale mirror

    $this->artisan('fincard:reconcile-balances')
        ->expectsOutputToContain('drift-corrected')
        ->assertExitCode(0);

    expect(FinCardAccount::query()->where('fincard_account_id', 'acc-1')->firstOrFail()->balance_cents)->toBe(1234);
});

it('skips when fincard is not the active issuer', function () {
    config(['cardissuance.default_issuer' => 'marqeta']);
    Http::fake();

    reconcileTestAccount(100);

    $this->artisan('fincard:reconcile-balances')
        ->expectsOutputToContain('not fincard')
        ->assertExitCode(0);

    Http::assertNothingSent();
    expect(FinCardAccount::query()->where('fincard_account_id', 'acc-1')->firstOrFail()->balance_cents)->toBe(100);
});
