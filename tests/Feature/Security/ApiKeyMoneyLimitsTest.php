<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Wallet\Workflows\WalletDepositWorkflow;
use App\Domain\Wallet\Workflows\WalletTransferWorkflow;
use App\Domain\Wallet\Workflows\WalletWithdrawWorkflow;
use App\Http\Middleware\EnforceApiKeyMoneyLimits;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Workflow\WorkflowStub;

/*
| An API key that may write can move its owner's money through the v2 API.
| Every key therefore carries three money controls, and they apply only
| when the request is authenticated by that key:
|
|   - the largest amount of one request (transfer_limit),
|   - the largest total per UTC day (daily_limit),
|   - the accounts it may move money from (allowed_account_uuids).
|
| Keys without their own values use API_KEY_TRANSFER_LIMIT_DEFAULT (1000)
| and API_KEY_DAILY_LIMIT_DEFAULT (5000), and no key goes above
| API_KEY_LIMIT_MAX (50000). Amounts count in hundredths of the major unit,
| rounded up, like the transaction rate limiter.
*/

beforeEach(function (): void {
    // CI runs some jobs on Redis; the daily counter lives in the cache.
    config(['cache.default' => 'array']);

    WorkflowStub::fake();
    WorkflowStub::mock(WalletWithdrawWorkflow::class, true);
    WorkflowStub::mock(WalletDepositWorkflow::class, true);

    $this->owner = User::factory()->create();
    $this->recipient = User::factory()->create();

    Asset::firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true],
    );

    $this->fromAccount = akmlAccount($this->owner, 10_000_000); // 100,000.00 USD
    $this->otherOwnAccount = akmlAccount($this->owner, 10_000_000);
    $this->toAccount = akmlAccount($this->recipient, 0);
});

function akmlAccount(User $user, int $cents): Account
{
    $account = Account::factory()->create(['user_uuid' => $user->uuid, 'frozen' => false]);
    AccountBalance::create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => $cents]);

    return $account;
}

/**
 * @param  array<string, mixed>  $settings
 */
function akmlKey(User $user, array $settings = []): string
{
    $result = ApiKey::createForUser($user, array_merge([
        'name'        => 'money key',
        'permissions' => ['read', 'write'],
    ], $settings));

    return $result['plain_key'];
}

/**
 * @return array<string, mixed>
 */
function akmlTransfer(Account $from, Account $to, string $amount): array
{
    return [
        'from_account_uuid' => $from->uuid,
        'to_account_uuid'   => $to->uuid,
        'amount'            => $amount,
        'asset_code'        => 'USD',
    ];
}

function akmlTransfersStarted(): int
{
    return DB::table('workflows')->where('class', WalletTransferWorkflow::class)->count();
}

describe('per-transfer limit', function (): void {
    it('uses the default of 1000 when the key has no limit of its own', function (): void {
        $key = akmlKey($this->owner);

        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '1000.00'))
            ->assertStatus(201);

        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '1000.01'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'API_KEY_TRANSFER_LIMIT_EXCEEDED')
            ->assertJsonPath('limit', '1000.00');

        expect(akmlTransfersStarted())->toBe(1);
    });

    it('uses the key\'s own limit', function (): void {
        $key = akmlKey($this->owner, ['transfer_limit' => '10.50']);

        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '10.50'))
            ->assertStatus(201);

        // 10.501 counts as 1051 hundredths (rounded up), above 1050.
        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '10.501'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'API_KEY_TRANSFER_LIMIT_EXCEEDED');

        expect(akmlTransfersStarted())->toBe(1);
    });

    it('never goes above the ceiling, even when a stored limit is higher', function (): void {
        $key = akmlKey($this->owner, ['transfer_limit' => '90000.00', 'daily_limit' => '90000.00']);

        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '50000.01'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'API_KEY_TRANSFER_LIMIT_EXCEEDED')
            ->assertJsonPath('limit', '50000.00');

        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '50000.00'))
            ->assertStatus(201);
    });

    it('follows the configured defaults and ceiling', function (): void {
        config([
            'rate_limiting.api_key_money.transfer_limit_default' => '200',
            'rate_limiting.api_key_money.daily_limit_default'    => '300',
            'rate_limiting.api_key_money.limit_max'              => '250',
        ]);

        $plain = akmlKey($this->owner);
        $apiKey = ApiKey::query()->where('user_uuid', $this->owner->uuid)->firstOrFail();

        // The daily default is above the ceiling, so the ceiling wins.
        expect($apiKey->effectiveTransferLimit())->toBe('200.00')
            ->and($apiKey->effectiveDailyLimit())->toBe('250.00');

        $this->withToken($plain)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '200.01'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'API_KEY_TRANSFER_LIMIT_EXCEEDED');
    });

    it('refuses an amount it cannot read instead of letting it through uncounted', function (): void {
        $key = akmlKey($this->owner);

        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, 'twelve'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'API_KEY_TRANSFER_LIMIT_EXCEEDED');

        expect(akmlTransfersStarted())->toBe(0);
    });
});

describe('daily limit', function (): void {
    it('uses the default of 5000 per UTC day', function (): void {
        $key = akmlKey($this->owner);

        foreach (range(1, 5) as $ignored) {
            $this->withToken($key)
                ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '1000.00'))
                ->assertStatus(201);
        }

        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '0.01'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'API_KEY_DAILY_LIMIT_EXCEEDED')
            ->assertJsonPath('limit', '5000.00')
            ->assertJsonPath('remaining_today', '0.00');

        expect(akmlTransfersStarted())->toBe(5);
    });

    it('counts per key, and starts again on the next UTC day', function (): void {
        $this->travelTo(now('UTC')->setTime(23, 0));

        $key = akmlKey($this->owner, ['transfer_limit' => '20', 'daily_limit' => '30']);
        $otherKey = akmlKey($this->owner, ['transfer_limit' => '20', 'daily_limit' => '30']);

        $this->withToken($key)->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '20.00'))->assertStatus(201);
        $this->withToken($key)->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '10.00'))->assertStatus(201);
        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '0.01'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'API_KEY_DAILY_LIMIT_EXCEEDED');

        // Another key of the same owner has its own counter.
        $this->withToken($otherKey)->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '20.00'))->assertStatus(201);

        $this->travelTo(now('UTC')->addDay()->setTime(0, 5));

        $this->withToken($key)->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '20.00'))->assertStatus(201);
    });

    it('does not count an idempotent replay twice', function (): void {
        $key = akmlKey($this->owner, ['transfer_limit' => '20', 'daily_limit' => '30']);
        $idempotencyKey = Str::uuid()->toString();
        $body = akmlTransfer($this->fromAccount, $this->toAccount, '20.00');

        $this->withToken($key)->postJson('/api/v2/transfers', $body, ['Idempotency-Key' => $idempotencyKey])
            ->assertStatus(201)
            ->assertHeader('X-Idempotency-Replayed', 'false');
        $this->withToken($key)->postJson('/api/v2/transfers', $body, ['Idempotency-Key' => $idempotencyKey])
            ->assertStatus(201)
            ->assertHeader('X-Idempotency-Replayed', 'true');

        // 20 used, so 10 more still fits; a counted replay would have refused it.
        $this->withToken($key)->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '10.00'))->assertStatus(201);
        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '0.01'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'API_KEY_DAILY_LIMIT_EXCEEDED');

        expect(akmlTransfersStarted())->toBe(2);
    });

    it('releases the reservation when the money action fails', function (): void {
        $poorAccount = akmlAccount($this->owner, 2000); // 20.00 USD
        $key = akmlKey($this->owner, ['transfer_limit' => '30', 'daily_limit' => '30']);

        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($poorAccount, $this->toAccount, '25.00'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'INSUFFICIENT_FUNDS');

        // The failed 25.00 no longer counts, so the full 30 is still available.
        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '30.00'))
            ->assertStatus(201);
    });

    it('does not count a refused per-transfer amount against the day', function (): void {
        $key = akmlKey($this->owner, ['transfer_limit' => '20', 'daily_limit' => '20']);

        $this->withToken($key)->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '25.00'))->assertStatus(422);
        $this->withToken($key)->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '20.00'))->assertStatus(201);
    });
});

describe('account pinning', function (): void {
    it('lets a pinned key move money only from its pinned accounts', function (): void {
        $key = akmlKey($this->owner, ['allowed_account_uuids' => [$this->fromAccount->uuid]]);

        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->otherOwnAccount, $this->toAccount, '5.00'))
            ->assertStatus(403)
            ->assertJsonPath('error', 'API_KEY_ACCOUNT_NOT_ALLOWED');

        $this->withToken($key)
            ->postJson('/api/v2/transfers', akmlTransfer($this->fromAccount, $this->toAccount, '5.00'))
            ->assertStatus(201);

        expect(akmlTransfersStarted())->toBe(1);
    });

    it('checks every source field the transfer accepts', function (): void {
        $key = akmlKey($this->owner, ['allowed_account_uuids' => [$this->fromAccount->uuid]]);

        $body = akmlTransfer($this->fromAccount, $this->toAccount, '5.00');
        $body['from_account'] = $this->otherOwnAccount->uuid;

        $this->withToken($key)
            ->postJson('/api/v2/transfers', $body)
            ->assertStatus(403)
            ->assertJsonPath('error', 'API_KEY_ACCOUNT_NOT_ALLOWED');
    });

    it('refuses an account the owner does not own, pinned or not', function (): void {
        $strangerAccount = akmlAccount($this->recipient, 10_000_000);

        $unpinned = akmlKey($this->owner);
        $this->withToken($unpinned)
            ->postJson('/api/v2/transfers', akmlTransfer($strangerAccount, $this->fromAccount, '5.00'))
            ->assertStatus(403)
            ->assertJsonPath('error', 'API_KEY_ACCOUNT_NOT_ALLOWED');

        // A pin that names someone else's account (written straight to the
        // database) still does not reach that account.
        $pinned = akmlKey($this->owner, ['allowed_account_uuids' => [$strangerAccount->uuid]]);
        $this->withToken($pinned)
            ->postJson('/api/v2/transfers', akmlTransfer($strangerAccount, $this->fromAccount, '5.00'))
            ->assertStatus(403)
            ->assertJsonPath('error', 'API_KEY_ACCOUNT_NOT_ALLOWED');

        expect(akmlTransfersStarted())->toBe(0);
    });

    it('applies the pin to the account in the path of deposit, withdraw and basket routes', function (string $path): void {
        $key = akmlKey($this->owner, ['allowed_account_uuids' => [$this->fromAccount->uuid]]);

        $this->withToken($key)
            ->postJson("/api/v2/accounts/{$this->otherOwnAccount->uuid}/{$path}", [
                'amount'      => '5',
                'asset_code'  => 'USD',
                'basket_code' => 'GCU',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'API_KEY_ACCOUNT_NOT_ALLOWED');
    })->with(['deposit', 'withdraw', 'baskets/compose', 'baskets/decompose']);

    it('refuses an external bank transfer to a pinned key, whose source is not one of its accounts', function (): void {
        $key = akmlKey($this->owner, ['allowed_account_uuids' => [$this->fromAccount->uuid]]);

        $this->withToken($key)
            ->postJson('/api/v2/banks/transfer', [
                'from_bank_code'  => 'PAYSERA',
                'from_account_id' => 'ext-1',
                'to_bank_code'    => 'REVOLUT',
                'to_account_id'   => 'ext-2',
                'amount'          => '5.00',
                'currency'        => 'EUR',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'API_KEY_ACCOUNT_NOT_ALLOWED');
    });
});

describe('other money routes', function (): void {
    it('applies the per-transfer limit to deposit and withdraw', function (string $path): void {
        $key = akmlKey($this->owner, ['transfer_limit' => '10']);

        $this->withToken($key)
            ->postJson("/api/v2/accounts/{$this->fromAccount->uuid}/{$path}", ['amount' => '10.01', 'asset_code' => 'USD'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'API_KEY_TRANSFER_LIMIT_EXCEEDED');
    })->with(['deposit', 'withdraw']);

    it('counts a basket amount, given in the basket\'s minor units, in hundredths of a basket unit', function (): void {
        Asset::firstOrCreate(
            ['code' => 'GCU'],
            ['name' => 'Global Currency Unit', 'type' => 'basket', 'precision' => 4, 'is_active' => true],
        );
        $key = akmlKey($this->owner, ['transfer_limit' => '10']);

        // 100001 at 4 decimals is 10.0001 units: 1000.01 hundredths, rounded up to 1001.
        $this->withToken($key)
            ->postJson("/api/v2/accounts/{$this->fromAccount->uuid}/baskets/compose", ['basket_code' => 'GCU', 'amount' => 100001])
            ->assertStatus(422)
            ->assertJsonPath('error', 'API_KEY_TRANSFER_LIMIT_EXCEEDED');
    });

    it('applies the per-transfer limit to external bank transfers', function (): void {
        $key = akmlKey($this->owner, ['transfer_limit' => '10']);

        $this->withToken($key)
            ->postJson('/api/v2/banks/transfer', [
                'from_bank_code'  => 'PAYSERA',
                'from_account_id' => 'ext-1',
                'to_bank_code'    => 'REVOLUT',
                'to_account_id'   => 'ext-2',
                'amount'          => '10.01',
                'currency'        => 'EUR',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'API_KEY_TRANSFER_LIMIT_EXCEEDED');
    });

    it('needs write permission for an external bank transfer', function (): void {
        $key = akmlKey($this->owner, ['permissions' => ['read']]);

        $this->withToken($key)
            ->postJson('/api/v2/banks/transfer', [
                'from_bank_code'  => 'PAYSERA',
                'from_account_id' => 'ext-1',
                'to_bank_code'    => 'REVOLUT',
                'to_account_id'   => 'ext-2',
                'amount'          => '5.00',
                'currency'        => 'EUR',
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient permissions');
    });

    it('guards every money-moving v2 route after the idempotency replay', function (string $uri): void {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn (RoutingRoute $route): bool => $route->uri() === $uri && in_array('POST', $route->methods(), true));
        assert($route instanceof RoutingRoute);

        $middleware = array_values(array_filter($route->gatherMiddleware(), 'is_string'));
        $guard = collect($middleware)->search(fn (string $m): bool => str_starts_with($m, EnforceApiKeyMoneyLimits::class));

        expect($guard)->toBeInt()
            ->and(array_search('idempotency', $middleware, true))->toBeLessThan((int) $guard);
    })->with([
        'transfer'         => ['api/v2/transfers'],
        'deposit'          => ['api/v2/accounts/{uuid}/deposit'],
        'withdraw'         => ['api/v2/accounts/{uuid}/withdraw'],
        'basket compose'   => ['api/v2/accounts/{uuid}/baskets/compose'],
        'basket decompose' => ['api/v2/accounts/{uuid}/baskets/decompose'],
        'bank transfer'    => ['api/v2/banks/transfer'],
    ]);
});

describe('sign-ins other than an API key', function (): void {
    it('leaves app users signed in with a token unaffected', function (): void {
        Sanctum::actingAs($this->owner, ['read', 'write', 'delete']);

        $this->postJson('/api/v2/transfers', akmlTransfer($this->otherOwnAccount, $this->toAccount, '2000.00'))
            ->assertStatus(201);
        $this->postJson('/api/v2/transfers', akmlTransfer($this->otherOwnAccount, $this->toAccount, '4000.00'))
            ->assertStatus(201);

        expect(akmlTransfersStarted())->toBe(2);
    });
});
