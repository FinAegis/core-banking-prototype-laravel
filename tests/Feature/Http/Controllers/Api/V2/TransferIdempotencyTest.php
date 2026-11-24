<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Wallet\Workflows\WalletDepositWorkflow;
use App\Domain\Wallet\Workflows\WalletTransferWorkflow;
use App\Domain\Wallet\Workflows\WalletWithdrawWorkflow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Workflow\WorkflowStub;

/*
| POST /api/v2/transfers is what the JavaScript, PHP and Python SDKs call
| when they create a transfer, and the SDKs retry it after a network error
| or a 5xx. A retry that carries the same Idempotency-Key must replay the
| first response and must not move money a second time.
|
| A transfer runs as a WalletTransferWorkflow that debits the sender through
| a WalletWithdrawWorkflow and credits the recipient through a
| WalletDepositWorkflow. The two child workflows are faked, so every debit
| and credit the server orders is recorded and can be counted.
*/

beforeEach(function (): void {
    WorkflowStub::fake();
    WorkflowStub::mock(WalletWithdrawWorkflow::class, true);
    WorkflowStub::mock(WalletDepositWorkflow::class, true);

    $this->sender = User::factory()->create();
    $this->recipient = User::factory()->create();

    $this->fromAccount = Account::factory()->create([
        'user_uuid' => $this->sender->uuid,
        'frozen'    => false,
    ]);
    $this->toAccount = Account::factory()->create([
        'user_uuid' => $this->recipient->uuid,
        'frozen'    => false,
    ]);

    Asset::firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true],
    );

    foreach ([$this->fromAccount, $this->toAccount] as $account) {
        AccountBalance::create([
            'account_uuid' => $account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 100000, // 1000.00 USD in cents
        ]);
    }

    Sanctum::actingAs($this->sender, ['read', 'write', 'delete']);
});

/**
 * @return array<string, mixed>
 */
function v2TransferBody(Account $from, Account $to, string $amount = '25.00'): array
{
    return [
        'from_account' => $from->uuid,
        'to_account'   => $to->uuid,
        'amount'       => $amount,
        'asset_code'   => 'USD',
        'reference'    => 'invoice-42',
    ];
}

function v2TransfersStarted(): int
{
    return DB::table('workflows')->where('class', WalletTransferWorkflow::class)->count();
}

/**
 * Debits the server ordered from the given account, in minor units.
 *
 * @return list<int>
 */
function v2DebitsFrom(Account $account): array
{
    /** @var list<int> $amounts */
    $amounts = WorkflowStub::dispatched(WalletWithdrawWorkflow::class)
        ->filter(fn (array $arguments): bool => (string) $arguments[0] === $account->uuid)
        ->map(fn (array $arguments): int => $arguments[2])
        ->values()
        ->all();

    return $amounts;
}

it('replays the first response and moves money once when the same key is sent twice', function (): void {
    $key = Str::uuid()->toString();
    $body = v2TransferBody($this->fromAccount, $this->toAccount);

    $first = $this->postJson('/api/v2/transfers', $body, ['Idempotency-Key' => $key]);
    $first->assertStatus(201);
    $first->assertHeader('X-Idempotency-Replayed', 'false');

    $second = $this->postJson('/api/v2/transfers', $body, ['Idempotency-Key' => $key]);

    $second->assertStatus(201);
    $second->assertHeader('X-Idempotency-Replayed', 'true');
    expect($second->json())->toBe($first->json());

    // One transfer, one debit of 25.00 USD, one credit.
    expect(v2TransfersStarted())->toBe(1)
        ->and(v2DebitsFrom($this->fromAccount))->toBe([2500]);
    WorkflowStub::assertDispatchedTimes(WalletDepositWorkflow::class, 1);
});

it('refuses the same key with a different body and moves no money for it', function (): void {
    $key = Str::uuid()->toString();

    $this->postJson('/api/v2/transfers', v2TransferBody($this->fromAccount, $this->toAccount), ['Idempotency-Key' => $key])
        ->assertStatus(201);

    $response = $this->postJson(
        '/api/v2/transfers',
        v2TransferBody($this->fromAccount, $this->toAccount, '40.00'),
        ['Idempotency-Key' => $key],
    );

    $response->assertStatus(409);
    $response->assertJsonPath('error', 'Idempotency key already used');

    expect(v2TransfersStarted())->toBe(1)
        ->and(v2DebitsFrom($this->fromAccount))->toBe([2500]);
});

it('does not share a key between users', function (): void {
    $key = Str::uuid()->toString();

    $this->postJson('/api/v2/transfers', v2TransferBody($this->fromAccount, $this->toAccount), ['Idempotency-Key' => $key])
        ->assertStatus(201);

    // The recipient happens to use the same key for a transfer of their own.
    // It is a different user, so it is a new request, not a replay.
    Sanctum::actingAs($this->recipient, ['read', 'write', 'delete']);
    $response = $this->postJson(
        '/api/v2/transfers',
        v2TransferBody($this->toAccount, $this->fromAccount, '5.00'),
        ['Idempotency-Key' => $key],
    );

    $response->assertStatus(201);
    $response->assertHeader('X-Idempotency-Replayed', 'false');
    expect(v2TransfersStarted())->toBe(2)
        ->and(v2DebitsFrom($this->fromAccount))->toBe([2500])
        ->and(v2DebitsFrom($this->toAccount))->toBe([500]);
});

it('keeps working without an Idempotency-Key: each request is a new transfer', function (): void {
    $body = v2TransferBody($this->fromAccount, $this->toAccount);

    $first = $this->postJson('/api/v2/transfers', $body);
    $second = $this->postJson('/api/v2/transfers', $body);

    $first->assertStatus(201)->assertHeaderMissing('X-Idempotency-Key');
    $second->assertStatus(201)->assertHeaderMissing('X-Idempotency-Key');
    expect($second->json('data.uuid'))->not->toBe($first->json('data.uuid'));

    expect(v2TransfersStarted())->toBe(2)
        ->and(v2DebitsFrom($this->fromAccount))->toBe([2500, 2500]);
});

it('deduplicates every v2 POST route that the SDKs call', function (string $uri): void {
    $route = collect(Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
        ->first(fn (Illuminate\Routing\Route $route): bool => $route->uri() === $uri && in_array('POST', $route->methods(), true));

    expect($route)->not->toBeNull();
    assert($route instanceof Illuminate\Routing\Route);

    $middleware = $route->gatherMiddleware();
    expect($middleware)->toContain('idempotency');

    // A replay must not count against the transaction limits a second time.
    $rateLimiter = array_search('transaction.rate_limit:transfer', $middleware, true);
    if ($rateLimiter !== false) {
        expect(array_search('idempotency', $middleware, true))->toBeLessThan($rateLimiter);
    }
})->with([
    'transfer'         => ['api/v2/transfers'],
    'deposit'          => ['api/v2/accounts/{uuid}/deposit'],
    'withdraw'         => ['api/v2/accounts/{uuid}/withdraw'],
    'basket compose'   => ['api/v2/accounts/{uuid}/baskets/compose'],
    'basket decompose' => ['api/v2/accounts/{uuid}/baskets/decompose'],
    'account create'   => ['api/v2/accounts'],
    'account freeze'   => ['api/v2/accounts/{uuid}/freeze'],
    'account unfreeze' => ['api/v2/accounts/{uuid}/unfreeze'],
    'basket create'    => ['api/v2/baskets'],
    'basket rebalance' => ['api/v2/baskets/{code}/rebalance'],
    'webhook create'   => ['api/v2/webhooks'],
]);

it('deduplicates the money-moving v2 POST routes that no SDK calls', function (string $uri, string $limiter): void {
    $route = collect(Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
        ->first(fn (Illuminate\Routing\Route $route): bool => $route->uri() === $uri && in_array('POST', $route->methods(), true));

    expect($route)->not->toBeNull();
    assert($route instanceof Illuminate\Routing\Route);

    $position = array_flip(array_filter($route->gatherMiddleware(), 'is_string'));

    // A replay must not count against the transaction limits a second time.
    expect($position)->toHaveKeys(['idempotency', $limiter])
        ->and($position['idempotency'])->toBeLessThan($position[$limiter]);
})->with([
    'GCU buy'       => ['api/v2/gcu/buy', 'transaction.rate_limit:convert'],
    'GCU sell'      => ['api/v2/gcu/sell', 'transaction.rate_limit:convert'],
    'bank transfer' => ['api/v2/banks/transfer', 'transaction.rate_limit:transfer'],
]);
