<?php

declare(strict_types=1);

use App\Domain\AI\MCP\Tools\SMS\SmsSendTool;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Services\CardProvisioningService;
use App\Domain\MachinePay\DataObjects\MppChallenge;
use App\Domain\MachinePay\Exceptions\MppSettlementException;
use App\Domain\MachinePay\Models\MppSpendingLimit;
use App\Domain\MachinePay\Services\MppClientService;
use App\Domain\MachinePay\Services\MppRailResolverService;
use App\Domain\SMS\Services\SmsPricingService;
use App\Domain\SMS\Services\SmsSendBudget;
use App\Domain\SMS\Services\SmsService;
use App\Domain\X402\Contracts\X402SignerInterface;
use App\Domain\X402\DataObjects\PaymentRequired;
use App\Domain\X402\DataObjects\PaymentRequirements;
use App\Domain\X402\DataObjects\ResourceInfo;
use App\Domain\X402\Models\X402SpendingLimit;
use App\Domain\X402\Services\X402ClientService;
use App\Domain\X402\Services\X402HeaderCodecService;
use App\Filament\Admin\Pages\Modules;
use App\Filament\Admin\Pages\PluginMarketplace;
use App\Infrastructure\Domain\DataObjects\InstallResult;
use App\Infrastructure\Domain\DomainManager;
use App\Infrastructure\Plugins\PluginManager;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;

/*
| Owner and operator rules for the REST card endpoints, the GraphQL
| rebalancePortfolio, verifyShards and Interledger mutations, team scope of
| x402 and MPP client spending limits, the is_admin route middleware, the
| runtime module/plugin management switch, operator-only treasury reads and
| Interledger REST, the MCP sms.send texts and the env templates.
*/

beforeEach(function (): void {
    Cache::flush();
    config(['cache.default' => 'array']);
});

function acfUserWithRole(string $role): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @param  array<string, mixed>  $variables
 * @return array<string, mixed>
 */
function acfGraphql(PHPUnit\Framework\TestCase $test, User $user, string $query, array $variables = []): array
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
function acfGraphqlError(array $json): ?string
{
    /** @var array<int, array{message?: string}> $errors */
    $errors = $json['errors'] ?? [];

    return $errors[0]['message'] ?? null;
}

/**
 * Create a demo card for the user through the REST endpoint and return its token.
 */
function acfCreateCard(PHPUnit\Framework\TestCase $test, User $owner): string
{
    Sanctum::actingAs($owner, ['read', 'write', 'delete']);
    $token = $test->postJson('/api/v1/cards', ['cardholder_name' => 'Card Owner'])
        ->assertCreated()
        ->json('data.card_token');
    expect($token)->toBeString();

    return (string) $token;
}

/**
 * Put the card in the state the endpoint acts on (only a frozen card can be
 * unfrozen).
 */
function acfPrepareCard(string $endpoint, string $token): void
{
    if ($endpoint === 'unfreeze') {
        expect(app(CardProvisioningService::class)->freezeCard($token))->toBeTrue();
    }
}

/**
 * Call one of the REST card endpoints as the user.
 *
 * @return TestResponse<Illuminate\Http\JsonResponse>
 */
function acfCardCall(PHPUnit\Framework\TestCase $test, User $user, string $endpoint, string $cardId): TestResponse
{
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $path = '/api/v1/cards/' . rawurlencode($cardId);

    return match ($endpoint) {
        'show'         => $test->getJson($path),
        'update'       => $test->patchJson($path, ['nickname' => 'Travel']),
        'transactions' => $test->getJson($path . '/transactions'),
        'freeze'       => $test->postJson($path . '/freeze'),
        'unfreeze'     => $test->deleteJson($path . '/freeze'),
        'cancel'       => $test->deleteJson($path, [
            'biometric_token' => hash_hmac('sha256', 'demo_biometric:' . $user->id, (string) config('app.key')),
            'reason'          => 'No longer needed',
        ]),
        default => throw new InvalidArgumentException($endpoint),
    };
}

/**
 * A user with a personal team and a spending limit for the shared agent id
 * stored under that team.
 *
 * @return array{0: User, 1: X402SpendingLimit}
 */
function acfTeamLimit(string $dailyLimit): array
{
    $user = User::factory()->withPersonalTeam()->create();
    $teamId = $user->currentTeam?->id;
    expect($teamId)->not->toBeNull();

    $limit = X402SpendingLimit::create([
        'agent_id'              => 'shared-agent',
        'agent_type'            => 'ai',
        'daily_limit'           => $dailyLimit,
        'spent_today'           => '0',
        'per_transaction_limit' => '50000',
        'auto_pay_enabled'      => true,
        'limit_resets_at'       => now()->addDay(),
        'team_id'               => $teamId,
    ]);

    return [$user, $limit];
}

describe('REST card endpoints by card id', function (): void {
    beforeEach(function (): void {
        config(['cardissuance.default_issuer' => 'demo']);
    });

    $endpoints = ['show', 'update', 'transactions', 'freeze', 'unfreeze', 'cancel'];

    it('serves the card owner', function (string $endpoint): void {
        $owner = User::factory()->create();
        $token = acfCreateCard($this, $owner);
        acfPrepareCard($endpoint, $token);

        acfCardCall($this, $owner, $endpoint, $token)
            ->assertOk()
            ->assertJsonPath('success', true);
    })->with($endpoints);

    it('answers another user like an unknown card', function (string $endpoint): void {
        $owner = User::factory()->create();
        $token = acfCreateCard($this, $owner);
        $other = User::factory()->create();

        $refused = acfCardCall($this, $other, $endpoint, $token);
        $refused->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ERR_CARD_NOT_FOUND');

        $unknown = acfCardCall($this, $other, $endpoint, 'card_' . Str::random(16));
        $unknown->assertNotFound();

        expect($refused->json())->toBe($unknown->json());
    })->with($endpoints);

    it('serves an operator', function (string $endpoint): void {
        $owner = User::factory()->create();
        $token = acfCreateCard($this, $owner);
        acfPrepareCard($endpoint, $token);

        acfCardCall($this, acfUserWithRole('bank_admin'), $endpoint, $token)
            ->assertOk()
            ->assertJsonPath('success', true);
    })->with($endpoints);

    it('answers 404 for an unknown card id, also to an operator', function (string $endpoint): void {
        $unknownId = 'card_' . Str::random(16);

        acfCardCall($this, User::factory()->create(), $endpoint, $unknownId)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ERR_CARD_NOT_FOUND');

        acfCardCall($this, acfUserWithRole('admin'), $endpoint, $unknownId)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ERR_CARD_NOT_FOUND');
    })->with($endpoints);

    it('leaves another user\'s card unchanged', function (string $endpoint): void {
        $owner = User::factory()->create();
        $token = acfCreateCard($this, $owner);

        acfCardCall($this, User::factory()->create(), $endpoint, $token)->assertNotFound();

        expect(app(CardProvisioningService::class)->getCard($token)?->status)->toBe(CardStatus::ACTIVE);
    })->with(['freeze', 'cancel']);

    it('keeps the owner response shapes', function (): void {
        $owner = User::factory()->create();
        $token = acfCreateCard($this, $owner);

        acfCardCall($this, $owner, 'show', $token)
            ->assertOk()
            ->assertJsonPath('data.card_token', $token)
            ->assertJsonStructure(['success', 'data' => ['card_token', 'last4', 'network', 'status', 'label']]);

        acfCardCall($this, $owner, 'update', $token)
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data'    => ['id' => $token, 'nickname' => 'Travel'],
                'message' => 'Card settings updated',
            ]);

        acfCardCall($this, $owner, 'transactions', $token)
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'success',
                'data'       => ['*' => ['id', 'amount', 'currency', 'merchant', 'category', 'status', 'timestamp']],
                'pagination' => ['next_cursor', 'has_more', 'total'],
            ]);

        acfCardCall($this, $owner, 'freeze', $token)
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Card frozen successfully']);

        acfCardCall($this, $owner, 'unfreeze', $token)
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Card unfrozen successfully']);

        acfCardCall($this, $owner, 'cancel', $token)
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Card cancelled successfully']);
    });

    it('accepts a card linked to the user by a local card record', function (): void {
        $owner = User::factory()->create();
        $token = acfCreateCard($this, User::factory()->create());
        $cardholder = Cardholder::create([
            'user_id'    => $owner->id,
            'first_name' => 'Card',
            'last_name'  => 'Owner',
            'kyc_status' => 'verified',
        ]);
        Card::query()->create([
            'user_id'           => $owner->id,
            'cardholder_id'     => $cardholder->id,
            'issuer_card_token' => $token,
            'issuer'            => 'demo',
            'last4'             => '4242',
            'network'           => 'visa',
            'status'            => 'active',
        ]);

        acfCardCall($this, $owner, 'show', $token)->assertOk()->assertJsonPath('data.card_token', $token);
    });

    it('still asks for authentication', function (): void {
        $this->getJson('/api/v1/cards/some-card')->assertUnauthorized();
        $this->getJson('/api/v1/cards/some-card/transactions')->assertUnauthorized();
        $this->postJson('/api/v1/cards/some-card/freeze')->assertUnauthorized();
    });
});

describe('GraphQL rebalancePortfolio', function (): void {
    beforeEach(function (): void {
        Cache::forget('domain_manager_disabled');
        config(['modules.disabled' => []]);
    });

    $mutation = 'mutation ($id: String!) { rebalancePortfolio(input: { portfolio_id: $id }) { portfolio_id target_amount } }';

    it('is refused to a user who is not an operator, without the portfolio data', function () use ($mutation): void {
        $portfolioId = Str::uuid()->toString();
        DB::table('asset_allocations')->insert([
            'portfolio_id'   => $portfolioId,
            'asset_class'    => 'equities',
            'target_weight'  => 60.0,
            'current_weight' => 55.0,
            'drift'          => -5.0,
            'target_amount'  => 60000.00,
            'current_amount' => 55000.00,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $json = acfGraphql($this, User::factory()->create(), $mutation, ['id' => $portfolioId]);

        expect(acfGraphqlError($json))->toBe('You are not authorized to rebalance this portfolio.')
            ->and($json['data'] ?? null)->toBeNull()
            ->and((string) json_encode($json))->not->toContain('60000');
    });

    it('is refused for an unknown portfolio in the same way', function () use ($mutation): void {
        $json = acfGraphql($this, User::factory()->create(), $mutation, ['id' => Str::uuid()->toString()]);

        expect(acfGraphqlError($json))->toBe('You are not authorized to rebalance this portfolio.');
    });

    it('lets an operator through', function () use ($mutation): void {
        $json = acfGraphql($this, acfUserWithRole('admin'), $mutation, ['id' => Str::uuid()->toString()]);

        expect(acfGraphqlError($json))->not->toBe('You are not authorized to rebalance this portfolio.');
    });
});

describe('GraphQL verifyShards', function (): void {
    $mutation = 'mutation ($uuid: String!) { verifyShards(input: { user_uuid: $uuid, key_version: 1, expected_public_key: "abc" }) }';

    it('verifies only the caller\'s own shards', function () use ($mutation): void {
        $caller = User::factory()->create();
        $other = User::factory()->create();

        $json = acfGraphql($this, $caller, $mutation, ['uuid' => (string) $other->uuid]);
        expect(acfGraphqlError($json))->toBe('You are not authorized to verify these shards.');

        $json = acfGraphql($this, $caller, $mutation, ['uuid' => (string) $caller->uuid]);
        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['verifyShards'])->toBeFalse();
    });

    it('lets an operator verify any user\'s shards', function () use ($mutation): void {
        $json = acfGraphql($this, acfUserWithRole('super_admin'), $mutation, ['uuid' => (string) User::factory()->create()->uuid]);

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['verifyShards'])->toBeFalse();
    });
});

describe('GraphQL Interledger mutations', function (): void {
    beforeEach(function (): void {
        Cache::forget('domain_manager_disabled');
        config(['modules.disabled' => []]);
    });

    $mutations = [
        'ilpCreateQuote'           => 'mutation { ilpCreateQuote(input: { send_asset: "USD", receive_asset: "EUR", send_amount: "10.00" }) { receive_amount } }',
        'ilpCreateIncomingPayment' => 'mutation { ilpCreateIncomingPayment(input: { wallet_address: "$ilp.example.com/alice", amount: "10.00", asset_code: "USD" }) }',
        'ilpCreateOutgoingPayment' => 'mutation { ilpCreateOutgoingPayment(input: { wallet_address: "$ilp.example.com/alice", quote_id: "q-1", grant_token: "g-1" }) { payment_id status } }',
    ];

    it('is refused to a user who is not an operator', function (string $mutation): void {
        $json = acfGraphql($this, User::factory()->create(), $mutation);

        expect(acfGraphqlError($json))->toBe('You are not authorized to use Interledger payments.')
            ->and($json['data'] ?? null)->toBeNull();
    })->with($mutations);

    it('lets an operator through', function (string $mutation): void {
        $json = acfGraphql($this, acfUserWithRole('bank_admin'), $mutation);

        expect($json)->not->toHaveKey('errors')
            ->and($json['data'])->not->toBeNull();
    })->with($mutations);
});

describe('x402 client spending limits', function (): void {
    beforeEach(function (): void {
        config([
            'x402.client.max_auto_pay_amount'            => '100000',
            'x402.agent_spending.require_approval_above' => '1000000',
        ]);

        /** @var X402SignerInterface&MockInterface $signer */
        $signer = Mockery::mock(X402SignerInterface::class);
        $signer->shouldReceive('signTransferAuthorization')->andReturn([
            'signature'     => '0xsig',
            'authorization' => [
                'from'        => '0xClient',
                'to'          => '0xRecipient',
                'value'       => '5000',
                'validAfter'  => '0',
                'validBefore' => (string) (time() + 60),
                'nonce'       => '0xnonce',
            ],
        ]);
        $signer->shouldReceive('getAddress')->andReturn('0xClient');

        $this->x402Client = new X402ClientService($signer, new X402HeaderCodecService());
        $this->x402Header = (new PaymentRequired(
            x402Version: 2,
            resource: new ResourceInfo(url: '/api/v1/premium/data', description: 'Premium data', mimeType: 'application/json'),
            accepts: [
                new PaymentRequirements(
                    scheme: 'exact',
                    network: 'eip155:8453',
                    asset: '0xUSDC',
                    amount: '5000',
                    payTo: '0xRecipient',
                    maxTimeoutSeconds: 60,
                ),
            ],
        ))->toBase64();
    });

    it('applies and charges the limit of the caller\'s team only', function (): void {
        [$generousUser, $generous] = acfTeamLimit('1000000');
        [$strictUser, $strict] = acfTeamLimit('1000');

        $this->actingAs($strictUser);
        expect(fn () => $this->x402Client->handlePaymentRequired($this->x402Header, 'shared-agent'))
            ->toThrow(RuntimeException::class, 'would exceed the daily limit');
        expect($generous->fresh()?->spent_today)->toBe('0');

        $this->actingAs($generousUser);
        $headers = $this->x402Client->handlePaymentRequired($this->x402Header, 'shared-agent');

        expect($headers)->toHaveKey('PAYMENT-SIGNATURE')
            ->and($generous->fresh()?->spent_today)->toBe('5000')
            ->and($strict->fresh()?->spent_today)->toBe('0');
    });

    it('does not use a team limit for a caller outside that team', function (): void {
        [, $teamLimit] = acfTeamLimit('1000000');
        config(['x402.client.max_auto_pay_amount' => '1000']);

        // No signed-in user: only limits without a team match, so the global
        // auto-pay cap applies.
        expect(fn () => $this->x402Client->handlePaymentRequired($this->x402Header, 'shared-agent'))
            ->toThrow(RuntimeException::class, 'exceeds the auto-pay limit');
        expect($teamLimit->fresh()?->spent_today)->toBe('0');
    });

    it('keeps using limits stored without a team', function (): void {
        $limit = X402SpendingLimit::create([
            'agent_id'              => 'solo-agent',
            'agent_type'            => 'ai',
            'daily_limit'           => '1000',
            'spent_today'           => '0',
            'per_transaction_limit' => '50000',
            'auto_pay_enabled'      => true,
            'limit_resets_at'       => now()->addDay(),
        ]);

        expect(fn () => $this->x402Client->handlePaymentRequired($this->x402Header, 'solo-agent'))
            ->toThrow(RuntimeException::class, 'would exceed the daily limit');
        expect($limit->fresh()?->spent_today)->toBe('0');
    });
});

describe('is_admin route middleware', function (): void {
    it('answers 401 without a token', function (): void {
        $this->postJson('/api/v2/modules/Exchange/verify')->assertUnauthorized();
        $this->postJson('/api/v1/mpp/resources', [])->assertUnauthorized();
    });

    it('answers 403 to a user who is not an admin', function (string $method, string $uri): void {
        Sanctum::actingAs(acfUserWithRole('bank_admin'), ['read', 'write', 'delete']);

        $this->json($method, $uri, [])
            ->assertForbidden()
            ->assertJsonPath('error', 'FORBIDDEN');
    })->with([
        'module verify'       => ['POST', '/api/v2/modules/Exchange/verify'],
        'module enable'       => ['POST', '/api/v2/modules/Exchange/enable'],
        'plugin discover'     => ['POST', '/api/v2/plugins/discover'],
        'monitoring workflow' => ['POST', '/api/monitoring/workflow/start'],
        'MPP resource create' => ['POST', '/api/v1/mpp/resources'],
        'MPP resource update' => ['PUT', '/api/v1/mpp/resources/1'],
        'MPP resource delete' => ['DELETE', '/api/v1/mpp/resources/1'],
    ]);

    it('lets an admin through', function (): void {
        Sanctum::actingAs(acfUserWithRole('admin'), ['read', 'write', 'delete']);

        $verify = $this->postJson('/api/v2/modules/Exchange/verify');
        expect($verify->status())->not->toBeIn([401, 403, 500]);

        // Reaches the controller's own validation.
        $this->postJson('/api/v1/mpp/resources', [])->assertUnprocessable();
    });

    it('keeps the MPP resource reads open to signed-in users', function (): void {
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $this->getJson('/api/v1/mpp/resources')->assertOk();
    });
});

describe('MCP sms.send texts', function (): void {
    it('describes the unpaid-send rules', function (): void {
        /** @var SmsService&MockInterface $service */
        $service = Mockery::mock(SmsService::class);
        /** @var SmsPricingService&MockInterface $pricing */
        $pricing = Mockery::mock(SmsPricingService::class);

        $description = (new SmsSendTool($service, $pricing, new SmsSendBudget()))->getDescription();

        expect($description)->not->toContain('requires payment')
            ->and($description)->toContain('signed-in user')
            ->and($description)->toContain('per user and across all users');
    });

    it('states the minimum paid price in the price-only note', function (): void {
        config(['sms.enabled' => true, 'sms.min_paid_price_usdc' => 25000]);

        /** @var SmsService&MockInterface $service */
        $service = Mockery::mock(SmsService::class);
        $service->shouldNotReceive('send');
        /** @var SmsPricingService&MockInterface $pricing */
        $pricing = Mockery::mock(SmsPricingService::class);
        $pricing->shouldReceive('getPriceForNumber')->andReturn([
            'amount_usdc'  => '48000',
            'rate_eur'     => '0.0440',
            'country_code' => 'LT',
            'parts'        => 1,
        ]);

        $result = (new SmsSendTool($service, $pricing, new SmsSendBudget()))
            ->execute(['to' => '+37069912345', 'message' => 'hello', 'check_price_only' => true]);

        expect($result->isSuccess())->toBeTrue();
        $note = (string) ($result->getData()['note'] ?? '');

        expect($note)->not->toContain('requires MPP payment')
            ->and($note)->toContain('at least 25000 atomic USDC')
            ->and($note)->toContain('per-user and all-users daily limits');
    });
});

/**
 * Evaluate config/modules.php with the given environment variables (null =
 * unset) and restore the process environment afterwards.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function acfModulesConfigUnderEnv(array $env): array
{
    $env = array_merge(['APP_ENV' => null, 'ADMIN_RUNTIME_MANAGEMENT_ENABLED' => null], $env);
    $saved = [];

    foreach ($env as $key => $value) {
        $saved[$key] = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key)];

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
        $config = require base_path('config/modules.php');

        return $config;
    } finally {
        foreach ($saved as $key => [$server, $envValue, $getenv]) {
            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }
            if ($envValue === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $envValue;
            }
            if ($getenv === false) {
                putenv($key);
            } else {
                putenv("{$key}={$getenv}");
            }
        }
    }
}

describe('runtime module and plugin management switch', function (): void {
    beforeEach(function (): void {
        Cache::forget('domain_manager_disabled');
        config(['modules.disabled' => []]);
    });

    it('is off by default under APP_ENV=production and on elsewhere', function (?string $appEnv, bool $expected): void {
        expect(acfModulesConfigUnderEnv(['APP_ENV' => $appEnv])['runtime_management_enabled'])->toBe($expected);
    })->with([
        'production'    => ['production', false],
        'APP_ENV unset' => [null, false],
        'local'         => ['local', true],
        'testing'       => ['testing', true],
        'demo'          => ['demo', true],
    ]);

    it('lets an explicit env value win', function (): void {
        expect(acfModulesConfigUnderEnv(['APP_ENV' => 'production', 'ADMIN_RUNTIME_MANAGEMENT_ENABLED' => 'true'])['runtime_management_enabled'])->toBeTrue()
            ->and(acfModulesConfigUnderEnv(['APP_ENV' => 'demo', 'ADMIN_RUNTIME_MANAGEMENT_ENABLED' => 'false'])['runtime_management_enabled'])->toBeFalse();
    });

    $writes = [
        'module enable'   => ['POST', '/api/v2/modules/Exchange/enable'],
        'module disable'  => ['POST', '/api/v2/modules/Exchange/disable'],
        'module verify'   => ['POST', '/api/v2/modules/Exchange/verify'],
        'plugin discover' => ['POST', '/api/v2/plugins/discover'],
        'plugin enable'   => ['POST', '/api/v2/plugins/1/enable'],
        'plugin disable'  => ['POST', '/api/v2/plugins/1/disable'],
        'plugin delete'   => ['DELETE', '/api/v2/plugins/1'],
        'plugin scan'     => ['POST', '/api/v2/plugins/1/scan'],
    ];

    it('answers 404 to an admin while off', function (string $method, string $uri): void {
        config(['modules.runtime_management_enabled' => false]);
        Sanctum::actingAs(acfUserWithRole('admin'), ['read', 'write', 'delete']);

        $this->json($method, $uri)->assertNotFound();
    })->with($writes);

    it('answers 404 to a user who is not an admin while off', function (string $method, string $uri): void {
        config(['modules.runtime_management_enabled' => false]);
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $this->json($method, $uri)->assertNotFound();
    })->with($writes);

    it('still answers 401 without a token while off', function (): void {
        config(['modules.runtime_management_enabled' => false]);

        $this->postJson('/api/v2/modules/Exchange/enable')->assertUnauthorized();
        $this->postJson('/api/v2/plugins/discover')->assertUnauthorized();
    });

    it('does not let an admin re-enable a module listed in MODULES_DISABLED while off', function (): void {
        config(['modules.runtime_management_enabled' => false, 'modules.disabled' => ['Exchange']]);
        Sanctum::actingAs(acfUserWithRole('super_admin'), ['read', 'write', 'delete']);

        $this->postJson('/api/v2/modules/Exchange/enable')->assertNotFound();

        expect(Cache::get('domain_manager_disabled'))->toBeNull()
            ->and(app(DomainManager::class)->isDisabled('Exchange'))->toBeTrue();
    });

    it('keeps the module and plugin reads available while off', function (): void {
        config(['modules.runtime_management_enabled' => false]);
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $this->getJson('/api/v2/modules')->assertOk();
        $this->getJson('/api/v2/plugins')->assertOk();
    });

    it('serves admins and refuses other users while on', function (): void {
        config(['modules.runtime_management_enabled' => true]);

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->postJson('/api/v2/modules/Exchange/verify')->assertForbidden();

        Sanctum::actingAs(acfUserWithRole('admin'), ['read', 'write', 'delete']);
        // Reaches the controller: an unknown module is its own 422.
        $this->postJson('/api/v2/modules/NoSuchModule/enable')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ENABLE_FAILED');
        $this->postJson('/api/v2/plugins/discover')->assertOk();
    });

    $pluginMutations = [
        'enablePlugin'  => 'mutation { enablePlugin(id: "999") { success message } }',
        'disablePlugin' => 'mutation { disablePlugin(id: "999") { success message } }',
        'scanPlugin'    => 'mutation { scanPlugin(id: "999") { plugin safe issue_count } }',
    ];

    it('refuses the GraphQL plugin mutations while off', function (string $mutation): void {
        config(['modules.runtime_management_enabled' => false]);

        $json = acfGraphql($this, acfUserWithRole('admin'), $mutation);

        expect(acfGraphqlError($json))->toBe('This operation is not available.')
            ->and($json['data'] ?? null)->toBeNull();
    })->with($pluginMutations);

    it('lets admins reach the GraphQL plugin mutations while on', function (string $mutation): void {
        config(['modules.runtime_management_enabled' => true]);

        $json = acfGraphql($this, acfUserWithRole('admin'), $mutation);
        expect(acfGraphqlError($json))->not->toBe('This operation is not available.')
            ->and(acfGraphqlError($json))->not->toBe('You are not authorized to manage plugins.');

        $json = acfGraphql($this, User::factory()->create(), $mutation);
        expect(acfGraphqlError($json))->toBe('You are not authorized to manage plugins.');
    })->with($pluginMutations);

    it('makes the admin panel module actions a no-op while off', function (): void {
        config(['modules.runtime_management_enabled' => false]);
        $this->actingAs(acfUserWithRole('admin'));

        /** @var DomainManager&MockInterface $manager */
        $manager = Mockery::mock(DomainManager::class);
        $manager->shouldNotReceive('enable');
        $manager->shouldNotReceive('disable');
        app()->instance(DomainManager::class, $manager);

        $page = new Modules();
        $page->enableModule('Exchange');
        $page->disableModule('Exchange');
    });

    it('runs the admin panel module actions while on', function (): void {
        config(['modules.runtime_management_enabled' => true]);
        $this->actingAs(acfUserWithRole('admin'));

        /** @var DomainManager&MockInterface $manager */
        $manager = Mockery::mock(DomainManager::class);
        $manager->shouldReceive('enable')->once()->with('Exchange')->andReturn(InstallResult::success('Exchange'));
        app()->instance(DomainManager::class, $manager);

        (new Modules())->enableModule('Exchange');
    });

    it('makes the admin panel plugin actions a no-op while off', function (): void {
        config(['modules.runtime_management_enabled' => false]);
        $this->actingAs(acfUserWithRole('admin'));

        /** @var PluginManager&MockInterface $manager */
        $manager = Mockery::mock(PluginManager::class);
        $manager->shouldNotReceive('enable');
        $manager->shouldNotReceive('disable');
        $manager->shouldNotReceive('discover');
        app()->instance(PluginManager::class, $manager);

        $page = new PluginMarketplace();
        $page->enablePlugin('finaegis', 'webhook-notifier');
        $page->disablePlugin('finaegis', 'webhook-notifier');
        $page->discoverPlugins();
    });

    it('runs the admin panel plugin discovery while on', function (): void {
        config(['modules.runtime_management_enabled' => true]);
        $this->actingAs(acfUserWithRole('admin'));

        /** @var PluginManager&MockInterface $manager */
        $manager = Mockery::mock(PluginManager::class);
        $manager->shouldReceive('discover')->once()->andReturn(['discovered' => 0, 'new' => 0]);
        app()->instance(PluginManager::class, $manager);

        (new PluginMarketplace())->discoverPlugins();
    });
});

describe('GraphQL treasury reads', function (): void {
    beforeEach(function (): void {
        Cache::forget('domain_manager_disabled');
        config(['modules.disabled' => []]);

        $this->allocationId = DB::table('asset_allocations')->insertGetId([
            'portfolio_id'   => Str::uuid()->toString(),
            'asset_class'    => 'equities',
            'target_weight'  => 60.0,
            'current_weight' => 55.0,
            'drift'          => -5.0,
            'target_amount'  => 60000.00,
            'current_amount' => 55000.00,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    });

    $portfolio = 'query ($id: ID!) { portfolio(id: $id) { id asset_class target_amount } }';
    $portfolios = '{ portfolios(first: 10, page: 1) { data { id asset_class target_amount } paginatorInfo { total } } }';

    it('returns nothing to a user who is not an operator', function () use ($portfolio, $portfolios): void {
        $user = User::factory()->create();

        $one = acfGraphql($this, $user, $portfolio, ['id' => (string) $this->allocationId]);
        expect($one)->not->toHaveKey('errors')
            ->and($one['data']['portfolio'])->toBeNull();

        $all = acfGraphql($this, $user, $portfolios);
        expect($all)->not->toHaveKey('errors')
            ->and($all['data']['portfolios']['data'])->toBe([])
            ->and($all['data']['portfolios']['paginatorInfo']['total'])->toBe(0)
            ->and((string) json_encode([$one, $all]))->not->toContain('60000');
    });

    it('returns the allocations to an operator', function () use ($portfolio, $portfolios): void {
        $operator = acfUserWithRole('bank_admin');

        $one = acfGraphql($this, $operator, $portfolio, ['id' => (string) $this->allocationId]);
        expect($one)->not->toHaveKey('errors')
            ->and($one['data']['portfolio']['asset_class'])->toBe('equities');

        $all = acfGraphql($this, $operator, $portfolios);
        expect($all)->not->toHaveKey('errors')
            ->and($all['data']['portfolios']['paginatorInfo']['total'])->toBe(1);
    });

    it('is closed while the Treasury module is disabled', function () use ($portfolio, $portfolios): void {
        config(['modules.disabled' => ['Treasury']]);
        $operator = acfUserWithRole('admin');

        expect(acfGraphqlError(acfGraphql($this, $operator, $portfolio, ['id' => (string) $this->allocationId])))
            ->toBe('This operation is not available.')
            ->and(acfGraphqlError(acfGraphql($this, $operator, $portfolios)))
            ->toBe('This operation is not available.');
    });
});

describe('REST Interledger endpoints', function (): void {
    $operatorOnly = [
        'quote'            => ['POST', '/api/v1/interledger/quotes', ['send_asset' => 'USD', 'receive_asset' => 'EUR', 'send_amount' => '10.00']],
        'incoming payment' => ['POST', '/api/v1/interledger/payments/incoming', ['wallet_address' => '$ilp.example.com/alice', 'amount' => '10.00', 'asset_code' => 'USD']],
        'outgoing payment' => ['POST', '/api/v1/interledger/payments/outgoing', ['wallet_address' => '$ilp.example.com/alice', 'quote_id' => '7f9c3c1e-3b7a-4d2f-9a51-5d1f0c6e8b21', 'grant_token' => 'g-1']],
        'payment status'   => ['GET', '/api/v1/interledger/payments/p-1', []],
        'open connection'  => ['POST', '/api/v1/interledger/connections', ['destination_address' => 'g.example.alice', 'shared_secret' => 'sssssssssssssssssssssssssssssssss']],
        'close connection' => ['DELETE', '/api/v1/interledger/connections/c-1', []],
    ];

    it('refuses a user who is not an operator', function (string $method, string $uri, array $body): void {
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $this->json($method, $uri, $body)->assertForbidden();
    })->with($operatorOnly);

    it('lets an operator through', function (string $method, string $uri, array $body): void {
        Sanctum::actingAs(acfUserWithRole('bank_admin'), ['read', 'write', 'delete']);

        expect($this->json($method, $uri, $body)->status())->not->toBeIn([401, 403, 404, 500]);
    })->with($operatorOnly);

    it('answers 401 without a token', function (): void {
        $this->postJson('/api/v1/interledger/quotes', [])->assertUnauthorized();
        $this->getJson('/api/v1/interledger/supported-assets')->assertUnauthorized();
    });

    it('keeps the supported-assets reference list open to signed-in users', function (): void {
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $this->getJson('/api/v1/interledger/supported-assets')->assertOk();
    });

    it('limits the GraphQL ilpPayment query to operators as well', function (): void {
        $query = '{ ilpPayment(id: "p-1") { payment_id status } }';

        $json = acfGraphql($this, User::factory()->create(), $query);
        expect(acfGraphqlError($json))->toBe('You are not authorized to use Interledger payments.');

        $json = acfGraphql($this, acfUserWithRole('admin'), $query);
        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['ilpPayment']['payment_id'])->toBe('p-1');
    });
});

describe('MPP client spending limits', function (): void {
    beforeEach(function (): void {
        config([
            'machinepay.client.enabled'                               => true,
            'machinepay.client.preferred_rails'                       => ['stripe'],
            'machinepay.agent_spending.default_daily_limit'           => 5000,
            'machinepay.agent_spending.default_per_transaction_limit' => 100,
        ]);

        /** @var MppRailResolverService&MockInterface $resolver */
        $resolver = Mockery::mock(MppRailResolverService::class);
        $resolver->shouldReceive('isRailAvailable')->andReturn(true);

        $this->mppClient = new MppClientService($resolver);
        $this->mppHeader = 'Payment ' . (new MppChallenge(
            id: 'ch-1',
            realm: 'api.example.com',
            intent: 'charge',
            resourceId: 'res-1',
            amountCents: 50,
            currency: 'USD',
            availableRails: ['stripe'],
            nonce: 'nonce-1',
            expiresAt: now()->addMinutes(5)->toIso8601String(),
        ))->toBase64Url();
    });

    /**
     * @return array{0: User, 1: int}
     */
    function acfTeamUser(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = $user->currentTeam?->id;
        expect($teamId)->not->toBeNull();

        return [$user, (int) $teamId];
    }

    /**
     * @param  array<model-property<MppSpendingLimit>, mixed>  $attributes
     */
    function acfMppLimit(string $agentId, ?int $teamId, array $attributes = []): MppSpendingLimit
    {
        $model = new MppSpendingLimit();
        $model->fill(array_merge([
            'agent_id'     => $agentId,
            'daily_limit'  => 5000,
            'per_tx_limit' => 100,
            'spent_today'  => 0,
            'auto_pay'     => true,
            'last_reset'   => gmdate('Y-m-d'),
            'team_id'      => $teamId,
        ], $attributes));
        $model->save();

        return $model;
    }

    it('applies and charges the limit stored under the caller\'s team', function (): void {
        [$user, $teamId] = acfTeamUser();
        $limit = acfMppLimit('team-agent', $teamId, ['daily_limit' => 40]);

        $this->actingAs($user);
        expect(fn () => $this->mppClient->handlePaymentRequired($this->mppHeader, 'team-agent'))
            ->toThrow(MppSettlementException::class, 'spending limit exceeded');

        $limit->update(['daily_limit' => 5000]);
        $this->mppClient->handlePaymentRequired($this->mppHeader, 'team-agent');

        expect($limit->fresh()?->spent_today)->toBe(50);
    });

    it('creates the default limit under the caller\'s team, never without a team', function (): void {
        [$user, $teamId] = acfTeamUser();

        $this->actingAs($user);
        $this->mppClient->handlePaymentRequired($this->mppHeader, 'new-agent');

        $rows = MppSpendingLimit::where('agent_id', 'new-agent')->get();
        expect($rows)->toHaveCount(1)
            ->and($rows->first()?->team_id)->toBe($teamId)
            ->and($rows->first()?->spent_today)->toBe(50);
    });

    it('never uses or charges another team\'s limit for the same agent id', function (): void {
        [, $otherTeamId] = acfTeamUser();
        $other = acfMppLimit('shared-agent', $otherTeamId, ['daily_limit' => 1000000, 'per_tx_limit' => 1000000]);
        [$caller, $callerTeamId] = acfTeamUser();

        $this->actingAs($caller);
        $this->mppClient->handlePaymentRequired($this->mppHeader, 'shared-agent');

        expect($other->fresh()?->spent_today)->toBe(0)
            ->and(MppSpendingLimit::where('agent_id', 'shared-agent')->where('team_id', $callerTeamId)->sole()->spent_today)->toBe(50);
    });

    it('does not let a team caller use a limit stored without a team', function (): void {
        $teamless = acfMppLimit('legacy-agent', null);
        [$caller, $callerTeamId] = acfTeamUser();

        $this->actingAs($caller);
        $this->mppClient->handlePaymentRequired($this->mppHeader, 'legacy-agent');

        expect($teamless->fresh()?->spent_today)->toBe(0)
            ->and(MppSpendingLimit::where('agent_id', 'legacy-agent')->where('team_id', $callerTeamId)->sole()->spent_today)->toBe(50);
    });

    it('keeps using limits stored without a team when there is no signed-in user', function (): void {
        $teamless = acfMppLimit('solo-agent', null);

        $this->mppClient->handlePaymentRequired($this->mppHeader, 'solo-agent');

        expect($teamless->fresh()?->spent_today)->toBe(50);
    });
});

describe('MPP spending-limit endpoints', function (): void {
    it('stores, lists and deletes limits under the caller\'s team only', function (): void {
        $owner = User::factory()->withPersonalTeam()->create();
        $other = User::factory()->withPersonalTeam()->create();

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/mpp/spending-limits', ['agent_id' => 'owner-agent', 'daily_limit' => 5000, 'per_tx_limit' => 100])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $row = MppSpendingLimit::where('agent_id', 'owner-agent')->first();
        expect($row)->not->toBeNull()
            ->and($row?->team_id)->toBe($owner->currentTeam?->id);

        Sanctum::actingAs($other, ['read', 'write', 'delete']);
        expect($this->getJson('/api/v1/mpp/spending-limits')->assertOk()->json('data'))->toBe([]);

        // Agent ids are per team: this creates the other team's own row.
        $this->postJson('/api/v1/mpp/spending-limits', ['agent_id' => 'owner-agent', 'daily_limit' => 999999, 'per_tx_limit' => 999999])
            ->assertCreated()
            ->assertJsonPath('data.team_id', $other->currentTeam?->id);
        $this->deleteJson('/api/v1/mpp/spending-limits/owner-agent')->assertOk();

        expect($row?->fresh()?->daily_limit)->toBe(5000);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        expect($this->getJson('/api/v1/mpp/spending-limits')->assertOk()->json('data.0.agent_id'))->toBe('owner-agent');

        $this->postJson('/api/v1/mpp/spending-limits', ['agent_id' => 'owner-agent', 'daily_limit' => 7000, 'per_tx_limit' => 100])
            ->assertCreated();
        expect($row?->fresh()?->daily_limit)->toBe(7000);

        $this->deleteJson('/api/v1/mpp/spending-limits/owner-agent')->assertOk();
        expect(MppSpendingLimit::where('agent_id', 'owner-agent')->exists())->toBeFalse();
    });
});

describe('MCP sms.send catalog texts', function (): void {
    it('does not describe sends as always paid', function (): void {
        /** @var array<string, array<string, mixed>> $tools */
        $tools = config('mcp.tools');
        /** @var array<string, string> $scopes */
        $scopes = config('mcp.scopes');
        $tool = $tools['sms.send'];
        $scope = $scopes['sms:send'];

        expect((string) $tool['title'])->not->toContain('paid')
            ->and($scope)->not->toContain('paid')
            ->and($scope)->toContain('daily send limits');

        /** @var SmsService&MockInterface $service */
        $service = Mockery::mock(SmsService::class);
        /** @var SmsPricingService&MockInterface $pricing */
        $pricing = Mockery::mock(SmsPricingService::class);

        $capabilities = (new SmsSendTool($service, $pricing, new SmsSendBudget()))->getCapabilities();

        expect($capabilities)->not->toContain('payment-required')
            ->and($capabilities)->not->toContain('mpp-gated')
            ->and($capabilities)->toContain('daily-limits');
    });
});

describe('env templates', function (): void {
    it('opens API sign-ups and keeps runtime management off in the Zelta template', function (): void {
        $template = (string) file_get_contents(base_path('.env.zelta.example'));

        expect($template)->toMatch('/^REGISTRATION_API_ENABLED=true$/m')
            ->and($template)->toMatch('/^REGISTRATION_API_INVITE_ONLY=false$/m')
            ->and($template)->toMatch('/^ADMIN_RUNTIME_MANAGEMENT_ENABLED=false$/m')
            ->and($template)->not->toContain('CLOSES NEW ZELTA SIGN-UPS');
    });

    it('keeps the secure defaults in the generic production template', function (): void {
        $template = (string) file_get_contents(base_path('.env.production.example'));

        expect($template)->toMatch('/^REGISTRATION_API_ENABLED=false$/m')
            ->and($template)->toMatch('/^ADMIN_RUNTIME_MANAGEMENT_ENABLED=false$/m');
    });
});
