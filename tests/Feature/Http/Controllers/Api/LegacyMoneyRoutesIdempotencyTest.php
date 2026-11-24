<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Wallet\Workflows\WalletDepositWorkflow;
use App\Domain\Wallet\Workflows\WalletWithdrawWorkflow;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Workflow\WorkflowStub;

/*
| The unversioned money routes (POST /api/transfers and
| /api/accounts/{uuid}/deposit and /withdraw) dedupe on Idempotency-Key. The
| PHP and Python SDKs reach /transfers here. The `idempotency` middleware
| must run before the transaction rate limiter, so a replayed request is
| answered from the stored response without counting against the hourly
| transfer and amount limits a second time.
*/

it('runs idempotency before the transaction rate limiter', function (string $uri, string $limiter): void {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RoutingRoute $route): bool => $route->uri() === $uri && in_array('POST', $route->methods(), true));

    expect($route)->not->toBeNull();
    assert($route instanceof RoutingRoute);

    $position = array_flip(array_filter($route->gatherMiddleware(), 'is_string'));

    expect($position)->toHaveKeys(['idempotency', $limiter])
        ->and($position['idempotency'])->toBeLessThan($position[$limiter]);
})->with([
    'transfer' => ['api/transfers', 'transaction.rate_limit:transfer'],
    'deposit'  => ['api/accounts/{uuid}/deposit', 'transaction.rate_limit:deposit'],
    'withdraw' => ['api/accounts/{uuid}/withdraw', 'transaction.rate_limit:withdraw'],
]);

it('does not count a replayed transfer against the rate and amount limits', function (): void {
    config(['rate_limiting.enabled' => true, 'rate_limiting.force_in_tests' => true]);
    WorkflowStub::fake();
    WorkflowStub::mock(WalletWithdrawWorkflow::class, true);
    WorkflowStub::mock(WalletDepositWorkflow::class, true);

    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $from = Account::factory()->create(['user_uuid' => $sender->uuid, 'frozen' => false]);
    $to = Account::factory()->create(['user_uuid' => $recipient->uuid, 'frozen' => false]);
    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    AccountBalance::create(['account_uuid' => $from->uuid, 'asset_code' => 'USD', 'balance' => 100000]);

    Sanctum::actingAs($sender, ['read', 'write', 'delete']);
    $key = Str::uuid()->toString();
    $body = ['from_account' => $from->uuid, 'to_account' => $to->uuid, 'amount' => '25.00', 'asset_code' => 'USD'];

    $this->postJson('/api/transfers', $body, ['Idempotency-Key' => $key])->assertStatus(201);
    $this->postJson('/api/transfers', $body, ['Idempotency-Key' => $key])
        ->assertStatus(201)
        ->assertHeader('X-Idempotency-Replayed', 'true');

    expect(Cache::get("tx_rate_limit:{$sender->id}:transfer:hourly"))->toBe(1)
        ->and(Cache::get("tx_rate_limit:{$sender->id}:transfer:daily"))->toBe(1)
        ->and(Cache::get("tx_amount_limit:{$sender->id}:transfer:hourly"))->toBe(2500);
    WorkflowStub::assertDispatchedTimes(WalletWithdrawWorkflow::class, 1);
});
