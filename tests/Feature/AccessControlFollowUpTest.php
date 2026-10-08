<?php

declare(strict_types=1);

use App\Domain\AI\MCP\Tools\SMS\SmsSendTool;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Services\CardProvisioningService;
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
| x402 client spending limits, the is_admin route middleware and the
| MCP sms.send texts.
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
