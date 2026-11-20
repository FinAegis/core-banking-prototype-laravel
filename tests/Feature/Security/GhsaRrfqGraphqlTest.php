<?php

declare(strict_types=1);

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketValue;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/*
| GHSA-rrfq-93h6-29wj, GraphQL cluster:
|  - D07: /graphql applies IP blocking and the token abilities of the api
|    middleware group (queries and subscriptions need `read`, mutations
|    `write`), with the same exemptions as EnforceMethodScope (`*` tokens and
|    session users);
|  - regulatory endpoint and filing schedule metadata is compliance-only;
|  - loan provisioning reads are operator-only;
|  - cancelTransfer answers the same for an unknown and a foreign transfer id;
|  - basket performance calculation is operator-only.
*/

const RRFQ_GQL_QUERY = '{ bankTransfers(first: 10) { id status } }';
const RRFQ_GQL_MUTATION = 'mutation ($id: String!) { cancelTransfer(transfer_id: $id) { transfer_id status message } }';

beforeEach(function (): void {
    // Keep counters, rate limiters and IP blocks out of the Redis cache that
    // CI's parallel processes share.
    Tests\Traits\SharedCacheIsolation::isolate();
    Cache::forget('domain_manager_disabled');
    config(['modules.disabled' => []]);
});

function rrfqGqlUser(?string $role = null): User
{
    $user = User::factory()->create();
    if ($role !== null) {
        Role::findOrCreate($role, 'web');
        $user->assignRole($role);
    }

    return $user;
}

/**
 * Post a GraphQL request (or a batch) as the currently authenticated user,
 * or with the given bearer token.
 *
 * @param  array<int|string, mixed>  $payload
 *
 * @return TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function rrfqGqlPost(PHPUnit\Framework\TestCase $test, array $payload, ?string $bearer = null): TestResponse
{
    $headers = [];
    if ($bearer !== null) {
        // A fresh guard per request, as in production: the Sanctum guard keeps
        // the user it resolved for the lifetime of the application instance.
        app('auth')->forgetGuards();
        $headers['Authorization'] = 'Bearer ' . $bearer;
    }

    return $test->postJson('/graphql', $payload, $headers);
}

/**
 * @param  array<string, mixed>  $variables
 * @return array<string, mixed>
 */
function rrfqGql(PHPUnit\Framework\TestCase $test, string $query, array $variables = [], ?string $bearer = null): array
{
    $response = rrfqGqlPost($test, ['query' => $query, 'variables' => $variables], $bearer);
    $response->assertOk();

    /** @var array<string, mixed> $json */
    $json = $response->json();

    return $json;
}

/**
 * @param  array<string, mixed>  $json
 */
function rrfqGqlError(array $json): ?string
{
    /** @var array<int, array{message?: string}> $errors */
    $errors = $json['errors'] ?? [];

    return $errors[0]['message'] ?? null;
}

function rrfqGqlTransfer(User $owner): string
{
    // bank_transfers.id is a uuid column (36 characters on MySQL).
    $id = Str::uuid()->toString();
    DB::table('bank_transfers')->insert([
        'id'              => $id,
        'user_uuid'       => $owner->uuid,
        'from_bank_code'  => 'TEST_BANK',
        'from_account_id' => 'acct-001',
        'to_bank_code'    => 'TEST_BANK',
        'to_account_id'   => 'acct-002',
        'amount'          => 50.00,
        'currency'        => 'EUR',
        'type'            => 'SEPA',
        'status'          => 'initiated',
        'metadata'        => json_encode(['status_history' => []]),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    return $id;
}

function rrfqGqlTransferStatus(string $id): ?string
{
    $status = DB::table('bank_transfers')->where('id', $id)->value('status');

    return is_string($status) ? $status : null;
}

/**
 * Assert that a query and a mutation both resolve for the caller.
 */
function rrfqGqlAssertQueryAndMutationWork(PHPUnit\Framework\TestCase $test, User $owner, ?string $bearer = null): void
{
    $id = rrfqGqlTransfer($owner);

    $json = rrfqGql($test, RRFQ_GQL_QUERY, [], $bearer);
    expect($json)->not->toHaveKey('errors')
        ->and(array_column($json['data']['bankTransfers'], 'id'))->toContain($id);

    $json = rrfqGql($test, RRFQ_GQL_MUTATION, ['id' => $id], $bearer);
    expect($json)->not->toHaveKey('errors')
        ->and($json['data']['cancelTransfer']['status'])->toBe('cancelled')
        ->and(rrfqGqlTransferStatus($id))->toBe('cancelled');
}

/**
 * @return array{endpoint: int, schedule: int}
 */
function rrfqGqlRegTechRows(): array
{
    $endpoint = DB::table('regulatory_endpoints')->insertGetId([
        'uuid'              => (string) Str::uuid(),
        'name'              => 'FinCEN filing',
        'regulator'         => 'FinCEN',
        'jurisdiction'      => 'US',
        'endpoint_type'     => 'filing',
        'base_url'          => 'https://regulator.example.test/api',
        'is_sandbox'        => true,
        'is_active'         => true,
        'health_status'     => 'healthy',
        'last_health_check' => now(),
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
    $schedule = DB::table('filing_schedules')->insertGetId([
        'uuid'          => (string) Str::uuid(),
        'name'          => 'Quarterly CTR summary',
        'report_type'   => 'CTR',
        'jurisdiction'  => 'US',
        'regulator'     => 'FinCEN',
        'frequency'     => 'quarterly',
        'deadline_days' => 30,
        'is_active'     => true,
        'auto_generate' => false,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    return ['endpoint' => (int) $endpoint, 'schedule' => (int) $schedule];
}

function rrfqGqlProvision(): void
{
    DB::table('mfi_loan_provisions')->insert([
        'id'               => (string) Str::uuid(),
        'loan_id'          => 'loan-001',
        'category'         => 'standard',
        'provision_amount' => '125.50',
        'days_overdue'     => 0,
        'review_date'      => now()->toDateString(),
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
}

function rrfqGqlBasket(): BasketAsset
{
    $basket = BasketAsset::factory()->create(['code' => 'RRFQ_BSK', 'is_active' => true]);
    BasketValue::factory()->create(['basket_asset_code' => $basket->code, 'value' => 1.0, 'calculated_at' => now()->subHours(6)]);
    BasketValue::factory()->create(['basket_asset_code' => $basket->code, 'value' => 1.1, 'calculated_at' => now()->subHour()]);

    return $basket;
}

describe('D07 token abilities on /graphql', function (): void {
    it('lets a read-only token query but not mutate', function (): void {
        $user = rrfqGqlUser();
        $id = rrfqGqlTransfer($user);
        Sanctum::actingAs($user, ['read']);

        $json = rrfqGql($this, RRFQ_GQL_QUERY);
        expect($json)->not->toHaveKey('errors')
            ->and(array_column($json['data']['bankTransfers'], 'id'))->toContain($id);

        $json = rrfqGql($this, RRFQ_GQL_MUTATION, ['id' => $id]);
        expect(rrfqGqlError($json))->toBe('Insufficient permissions. Required scope: write')
            ->and($json['errors'][0]['extensions']['code'] ?? null)->toBe('INSUFFICIENT_SCOPE')
            ->and($json['errors'][0]['extensions']['required_scope'] ?? null)->toBe('write')
            ->and($json['data'] ?? null)->toBeNull()
            ->and(rrfqGqlTransferStatus($id))->toBe('initiated');
    });

    it('refuses queries and mutations to a refresh-only token', function (): void {
        $user = rrfqGqlUser();
        $id = rrfqGqlTransfer($user);
        $refresh = $user->createToken('web-refresh', ['refresh'])->plainTextToken;

        $json = rrfqGql($this, RRFQ_GQL_QUERY, [], $refresh);
        expect(rrfqGqlError($json))->toBe('Insufficient permissions. Required scope: read')
            ->and($json['data'] ?? null)->toBeNull();

        $json = rrfqGql($this, RRFQ_GQL_MUTATION, ['id' => $id], $refresh);
        expect(rrfqGqlError($json))->toBe('Insufficient permissions. Required scope: write')
            ->and(rrfqGqlTransferStatus($id))->toBe('initiated');
    });

    it('lets a token with read, write and delete query and mutate', function (): void {
        $user = rrfqGqlUser();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        rrfqGqlAssertQueryAndMutationWork($this, $user);
    });

    it('lets a wildcard token query and mutate', function (): void {
        $user = rrfqGqlUser();
        // createToken() without abilities issues ['*'].
        $token = $user->createToken('legacy')->plainTextToken;

        rrfqGqlAssertQueryAndMutationWork($this, $user, $token);
    });

    it('keeps a session-authenticated user working', function (): void {
        $user = rrfqGqlUser();
        $this->actingAs($user, 'web');

        rrfqGqlAssertQueryAndMutationWork($this, $user);
    });

    it('accepts the access token of a password login and refuses its refresh token', function (): void {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);

        $login = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'correct-horse-battery',
        ]);
        $login->assertOk();
        $access = (string) $login->json('data.access_token');
        $refresh = (string) $login->json('data.refresh_token');

        expect(PersonalAccessToken::findToken($access)?->abilities)->toBe(['read', 'write'])
            ->and(PersonalAccessToken::findToken($refresh)?->abilities)->toBe(['refresh']);

        rrfqGqlAssertQueryAndMutationWork($this, $user, $access);

        $id = rrfqGqlTransfer($user);
        $json = rrfqGql($this, RRFQ_GQL_MUTATION, ['id' => $id], $refresh);
        expect(rrfqGqlError($json))->toBe('Insufficient permissions. Required scope: write')
            ->and(rrfqGqlTransferStatus($id))->toBe('initiated');
    });

    it('accepts the token shape issued by the Privy login', function (): void {
        $user = rrfqGqlUser();
        // LoginController::privyLogin issues ['read', 'write', 'delete'].
        $token = $user->createToken('privy', ['read', 'write', 'delete'])->plainTextToken;

        rrfqGqlAssertQueryAndMutationWork($this, $user, $token);
    });

    it('treats the create and update permissions of dashboard API tokens as write', function (): void {
        $user = rrfqGqlUser();
        $all = $user->createToken('dashboard', ['create', 'read', 'update', 'delete'])->plainTextToken;
        rrfqGqlAssertQueryAndMutationWork($this, $user, $all);

        // The dashboard default is ['read'].
        $readOnly = $user->createToken('dashboard-read', ['read'])->plainTextToken;
        $id = rrfqGqlTransfer($user);
        $json = rrfqGql($this, RRFQ_GQL_MUTATION, ['id' => $id], $readOnly);
        expect(rrfqGqlError($json))->toBe('Insufficient permissions. Required scope: write')
            ->and(rrfqGqlTransferStatus($id))->toBe('initiated');
    });

    it('checks each operation of a batched request', function (): void {
        $user = rrfqGqlUser();
        $id = rrfqGqlTransfer($user);
        Sanctum::actingAs($user, ['read']);

        $response = rrfqGqlPost($this, [
            ['query' => RRFQ_GQL_QUERY],
            ['query' => RRFQ_GQL_MUTATION, 'variables' => ['id' => $id]],
        ]);
        $response->assertOk();

        expect($response->json('0.errors'))->toBeNull()
            ->and(array_column($response->json('0.data.bankTransfers'), 'id'))->toContain($id)
            ->and($response->json('1.errors.0.message'))->toBe('Insufficient permissions. Required scope: write')
            ->and(rrfqGqlTransferStatus($id))->toBe('initiated');
    });

    it('checks a mutation sent as an automatic persisted query', function (): void {
        $user = rrfqGqlUser();
        $hash = hash('sha256', RRFQ_GQL_MUTATION);
        $extensions = ['persistedQuery' => ['version' => 1, 'sha256Hash' => $hash]];

        // A full token registers the document by sending it once.
        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $first = rrfqGqlTransfer($user);
        $response = rrfqGqlPost($this, ['query' => RRFQ_GQL_MUTATION, 'variables' => ['id' => $first], 'extensions' => $extensions]);
        expect($response->json('data.cancelTransfer.status'))->toBe('cancelled');

        // A read-only token sends only the hash.
        Sanctum::actingAs($user, ['read']);
        $second = rrfqGqlTransfer($user);
        $response = rrfqGqlPost($this, ['variables' => ['id' => $second], 'extensions' => $extensions]);
        expect($response->json('errors.0.message'))->toBe('Insufficient permissions. Required scope: write')
            ->and(rrfqGqlTransferStatus($second))->toBe('initiated');
    });

    it('lets a read token subscribe and refuses a refresh-only token', function (): void {
        config(['lighthouse.subscriptions.broadcaster' => 'log']);
        $user = rrfqGqlUser();
        $subscription = 'subscription { tradeExecuted(pair: "BTC/EUR") { trade_id } }';

        $refresh = $user->createToken('web-refresh', ['refresh'])->plainTextToken;
        $json = rrfqGql($this, $subscription, [], $refresh);
        expect(rrfqGqlError($json))->toBe('Insufficient permissions. Required scope: read');

        $read = $user->createToken('reader', ['read'])->plainTextToken;
        $json = rrfqGql($this, $subscription, [], $read);
        expect(rrfqGqlError($json))->toBeNull();
    });
});

describe('D07 IP blocking on /graphql', function (): void {
    it('refuses a temporarily blocked IP before any field runs', function (): void {
        $user = rrfqGqlUser();
        $id = rrfqGqlTransfer($user);
        Cache::put('ip_blocked:203.0.113.7', now()->addHour(), 3600);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson('/graphql', ['query' => RRFQ_GQL_MUTATION, 'variables' => ['id' => $id]]);

        $response->assertStatus(403);
        expect($response->json('error'))->toBe('Access Denied')
            ->and(rrfqGqlTransferStatus($id))->toBe('initiated');
    });

    it('refuses a permanently blacklisted range', function (): void {
        $user = rrfqGqlUser();
        Cache::forever('ip_blacklist', ['198.51.100.0/24']);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson('/graphql', ['query' => RRFQ_GQL_QUERY])
            ->assertStatus(403);
    });

    it('serves an IP that is not blocked', function (): void {
        $user = rrfqGqlUser();
        Cache::put('ip_blocked:203.0.113.7', now()->addHour(), 3600);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8']);

        rrfqGqlAssertQueryAndMutationWork($this, $user);
    });
});

describe('regulatory endpoint and filing schedule metadata is compliance-only', function (): void {
    $query = 'query ($e: ID!, $s: ID!) {
        regulatoryEndpoint(id: $e) { id base_url }
        regulatoryEndpoints(first: 10) { data { id } }
        filingSchedule(id: $s) { id name }
        filingSchedules(first: 10) { data { id } }
        endpointHealth { id }
    }';

    it('gives a customer no rows', function () use ($query): void {
        $ids = rrfqGqlRegTechRows();
        Sanctum::actingAs(rrfqGqlUser(), ['read', 'write', 'delete']);

        $json = rrfqGql($this, $query, ['e' => $ids['endpoint'], 's' => $ids['schedule']]);

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['regulatoryEndpoint'])->toBeNull()
            ->and($json['data']['regulatoryEndpoints']['data'])->toBe([])
            ->and($json['data']['filingSchedule'])->toBeNull()
            ->and($json['data']['filingSchedules']['data'])->toBe([])
            ->and($json['data']['endpointHealth'])->toBe([]);
    });

    it('shows the rows to compliance staff', function (string $role) use ($query): void {
        $ids = rrfqGqlRegTechRows();
        Sanctum::actingAs(rrfqGqlUser($role), ['read', 'write', 'delete']);

        $json = rrfqGql($this, $query, ['e' => $ids['endpoint'], 's' => $ids['schedule']]);

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['regulatoryEndpoint']['base_url'])->toBe('https://regulator.example.test/api')
            ->and(array_column($json['data']['regulatoryEndpoints']['data'], 'id'))->toBe([(string) $ids['endpoint']])
            ->and($json['data']['filingSchedule']['name'])->toBe('Quarterly CTR summary')
            ->and(array_column($json['data']['filingSchedules']['data'], 'id'))->toBe([(string) $ids['schedule']])
            ->and(array_column($json['data']['endpointHealth'], 'id'))->toBe([(string) $ids['endpoint']]);
    })->with(['compliance_officer', 'admin']);
});

describe('loan provisioning reads are operator-only', function (): void {
    it('refuses a customer over REST and GraphQL', function (): void {
        rrfqGqlProvision();
        Sanctum::actingAs(rrfqGqlUser(), ['read', 'write', 'delete']);

        $this->getJson('/api/v1/microfinance/provisioning')
            ->assertForbidden()
            ->assertJsonMissingPath('data');
        $this->getJson('/api/v1/microfinance/provisioning/standard')
            ->assertForbidden()
            ->assertJsonMissingPath('data');

        $json = rrfqGql($this, '{ mfiProvisionSummary { total } }');
        expect(rrfqGqlError($json))->toBe('You are not authorized to view loan provisions.')
            ->and($json['data'] ?? null)->toBeNull();
    });

    it('serves an operator over REST and GraphQL', function (): void {
        rrfqGqlProvision();
        Sanctum::actingAs(rrfqGqlUser('bank_admin'), ['read', 'write', 'delete']);

        $this->getJson('/api/v1/microfinance/provisioning')
            ->assertOk()
            ->assertJsonPath('data.standard', '125.50')
            ->assertJsonPath('data.total', '125.50');
        $this->getJson('/api/v1/microfinance/provisioning/standard')
            ->assertOk()
            ->assertJsonPath('data.0.loan_id', 'loan-001');

        $json = rrfqGql($this, '{ mfiProvisionSummary { total } }');
        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['mfiProvisionSummary']['total'])->toBe('125.50');
    });
});

describe('cancelTransfer does not reveal whether a transfer id exists', function (): void {
    it('answers the same for a foreign and an unknown transfer id', function (): void {
        $owner = rrfqGqlUser();
        $foreign = rrfqGqlTransfer($owner);
        Sanctum::actingAs(rrfqGqlUser(), ['read', 'write', 'delete']);

        $foreignJson = rrfqGql($this, RRFQ_GQL_MUTATION, ['id' => $foreign]);
        $unknownJson = rrfqGql($this, RRFQ_GQL_MUTATION, ['id' => '00000000-0000-4000-8000-0000000000aa']);

        expect($foreignJson)->not->toHaveKey('errors')
            ->and($foreignJson['data']['cancelTransfer'])->toBe([
                'transfer_id' => $foreign,
                'status'      => 'not_found',
                'message'     => 'Transfer not found.',
            ])
            ->and($unknownJson['data']['cancelTransfer'])->toBe([
                'transfer_id' => '00000000-0000-4000-8000-0000000000aa',
                'status'      => 'not_found',
                'message'     => 'Transfer not found.',
            ])
            ->and(rrfqGqlTransferStatus($foreign))->toBe('initiated');
    });

    it('still lets the owner and an operator cancel', function (): void {
        $owner = rrfqGqlUser();

        $first = rrfqGqlTransfer($owner);
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        expect(rrfqGql($this, RRFQ_GQL_MUTATION, ['id' => $first])['data']['cancelTransfer']['status'])->toBe('cancelled');

        $second = rrfqGqlTransfer($owner);
        Sanctum::actingAs(rrfqGqlUser('bank_admin'), ['read', 'write', 'delete']);
        expect(rrfqGql($this, RRFQ_GQL_MUTATION, ['id' => $second])['data']['cancelTransfer']['status'])->toBe('cancelled')
            ->and(rrfqGqlTransferStatus($second))->toBe('cancelled');

        Sanctum::actingAs(rrfqGqlUser('bank_admin'), ['read', 'write', 'delete']);
        expect(rrfqGql($this, RRFQ_GQL_MUTATION, ['id' => '00000000-0000-4000-8000-0000000000aa'])['data']['cancelTransfer']['status'])->toBe('not_found');
    });
});

describe('basket performance calculation is operator-only', function (): void {
    it('refuses a customer and calculates nothing', function (): void {
        $basket = rrfqGqlBasket();
        Sanctum::actingAs(rrfqGqlUser(), ['read', 'write', 'delete']);

        $this->postJson("/api/v2/baskets/{$basket->code}/performance/calculate", ['period' => 'day'])
            ->assertForbidden();

        expect(DB::table('basket_performances')->count())->toBe(0);
    });

    it('lets an operator calculate', function (): void {
        // No basket values: the calculation itself has nothing to work on
        // (a separate functional bug breaks it when values exist), so this
        // checks the access decision only.
        $basket = BasketAsset::factory()->create(['code' => 'RRFQ_BSK', 'is_active' => true]);
        Sanctum::actingAs(rrfqGqlUser('admin'), ['read', 'write', 'delete']);

        $this->postJson("/api/v2/baskets/{$basket->code}/performance/calculate", ['period' => 'day'])
            ->assertOk()
            ->assertJsonPath('message', 'Performance calculation completed')
            ->assertJsonPath('calculated_periods', []);
    });
});
