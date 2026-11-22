<?php

declare(strict_types=1);

use App\Domain\MachinePay\DataObjects\MppChallenge;
use App\Domain\MachinePay\Models\MppPayment;
use App\Domain\MachinePay\Models\MppSpendingLimit;
use App\Domain\MachinePay\Services\MppClientService;
use App\Domain\MachinePay\Services\MppRailResolverService;
use App\Domain\X402\Contracts\X402SignerInterface;
use App\Domain\X402\DataObjects\PaymentRequired;
use App\Domain\X402\DataObjects\PaymentRequirements;
use App\Domain\X402\DataObjects\ResourceInfo;
use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Domain\X402\Models\X402Payment;
use App\Domain\X402\Models\X402SpendingLimit;
use App\Domain\X402\Services\X402ClientService;
use App\Domain\X402\Services\X402HeaderCodecService;
use App\Domain\X402\Services\X402PricingService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;

/*
| GHSA-385w-q6j4-5qw6, machine payments (MPP and x402):
| - C06/C24: payment history and stats are limited to the caller's team
|   (every row for platform administrators, nothing for a caller without a
|   team) instead of being global or matched through team_id IS NULL.
| - C25: administrators see and manage every monetized endpoint (prices
|   apply platform-wide) and the payment gate picks a matching price row
|   deterministically.
| - C36: callers without a team keep their own spending limits instead of
|   sharing the team_id NULL rows.
| - D08: MPP agent ids are unique per team (like x402), so one team cannot
|   block another team's agent id.
*/

beforeEach(function (): void {
    Cache::flush();
    config(['cache.default' => 'array']);
});

function ghsaMpTeamUser(): User
{
    $user = User::factory()->withPersonalTeam()->create();
    expect($user->currentTeam)->not->toBeNull();

    return $user;
}

function ghsaMpTeamlessUser(): User
{
    $user = User::factory()->create();
    expect($user->currentTeam)->toBeNull();

    return $user;
}

function ghsaMpAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user = ghsaMpTeamUser();
    $user->assignRole('admin');

    return $user;
}

function ghsaMpTeamId(User $user): int
{
    return (int) $user->currentTeam?->id;
}

function ghsaMpActAs(User $user): void
{
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
}

function ghsaMpMppPayment(?int $teamId, int $amountCents = 500): MppPayment
{
    return MppPayment::create([
        'uuid'             => (string) Str::uuid(),
        'challenge_id'     => 'ch_' . Str::random(12),
        'rail'             => 'stripe',
        'amount_cents'     => $amountCents,
        'currency'         => 'USD',
        'status'           => 'settled',
        'payer_identifier' => 'did:example:payer',
        'endpoint_method'  => '',
        'endpoint_path'    => 'POST:/api/v1/sms/send',
        'payment_payload'  => ['challenge_id' => 'ch_x', 'proof_of_payment' => ['spt' => 'spt_secret_token']],
        'payload_hash'     => hash('sha256', Str::random(32)),
        'team_id'          => $teamId,
    ]);
}

function ghsaMpX402Payment(?int $teamId, string $amount = '1000000'): X402Payment
{
    return X402Payment::create([
        'payer_address'   => '0x' . Str::lower(Str::random(40)),
        'pay_to_address'  => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
        'amount'          => $amount,
        'network'         => 'eip155:8453',
        'asset'           => 'USDC',
        'scheme'          => 'exact',
        'status'          => 'settled',
        'endpoint_method' => 'POST',
        'endpoint_path'   => '/api/v1/sms/send',
        'team_id'         => $teamId,
    ]);
}

/**
 * @param  array<model-property<X402MonetizedEndpoint>, mixed>  $overrides
 */
function ghsaMpEndpoint(?int $teamId, array $overrides = []): X402MonetizedEndpoint
{
    $model = new X402MonetizedEndpoint();
    $model->fill(array_merge([
        'method'    => 'GET',
        'path'      => '/api/v1/premium/' . Str::random(6),
        'price'     => '0.01',
        'network'   => 'eip155:8453',
        'is_active' => true,
        'team_id'   => $teamId,
    ], $overrides));
    $model->save();

    return $model;
}

/**
 * @param  array<model-property<X402SpendingLimit>, mixed>  $overrides
 */
function ghsaMpX402Limit(string $agentId, ?int $teamId, ?int $userId, array $overrides = []): X402SpendingLimit
{
    $model = new X402SpendingLimit();
    $model->fill(array_merge([
        'agent_id'              => $agentId,
        'agent_type'            => 'ai',
        'daily_limit'           => '1000',
        'spent_today'           => '0',
        'per_transaction_limit' => '50000',
        'auto_pay_enabled'      => true,
        'limit_resets_at'       => now()->addDay(),
        'team_id'               => $teamId,
        'user_id'               => $userId,
    ], $overrides));
    $model->save();

    return $model;
}

/**
 * @param  array<model-property<MppSpendingLimit>, mixed>  $overrides
 */
function ghsaMpMppLimit(string $agentId, ?int $teamId, ?int $userId, array $overrides = []): MppSpendingLimit
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
        'user_id'      => $userId,
    ], $overrides));
    $model->save();

    return $model;
}

/**
 * @param  array<string, mixed>  $variables
 * @return array<string, mixed>
 */
function ghsaMpGraphql(PHPUnit\Framework\TestCase $test, User $user, string $query, array $variables = []): array
{
    ghsaMpActAs($user);
    $response = $test->postJson('/graphql', ['query' => $query, 'variables' => $variables]);
    $response->assertOk();

    /** @var array<string, mixed> $json */
    $json = $response->json();

    return $json;
}

// ---------------------------------------------------------------------------
// C06: MPP payment history and stats
// ---------------------------------------------------------------------------

describe('C06 MPP payment history', function (): void {
    beforeEach(function (): void {
        $this->owner = ghsaMpTeamUser();
        $this->other = ghsaMpTeamUser();
        $this->ownPayment = ghsaMpMppPayment(ghsaMpTeamId($this->owner), 300);
        $this->otherPayment = ghsaMpMppPayment(ghsaMpTeamId($this->other), 700);
        $this->platformPayment = ghsaMpMppPayment(null, 1100);
    });

    it('lists nothing to a caller without a team', function (): void {
        ghsaMpActAs(ghsaMpTeamlessUser());

        $this->getJson('/api/v1/mpp/payments')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.data', []);

        $this->getJson('/api/v1/mpp/payments/stats')
            ->assertOk()
            ->assertJsonPath('data.total_payments', 0)
            ->assertJsonPath('data.settled', 0)
            ->assertJsonPath('data.total_settled_cents', 0)
            ->assertJsonPath('data.by_rail', []);

        expect(MppPayment::query()->count())->toBe(3);
    });

    it('lists only the caller\'s team payments, without the payment proof', function (): void {
        ghsaMpActAs($this->owner);

        $response = $this->getJson('/api/v1/mpp/payments')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.uuid', $this->ownPayment->uuid);

        expect($response->json('data.data.0'))->not->toHaveKey('payment_payload')
            ->and($response->json('data.data.0'))->not->toHaveKey('payload_hash')
            ->and($response->getContent())->not->toContain('spt_secret_token');

        $this->getJson('/api/v1/mpp/payments/stats')
            ->assertOk()
            ->assertJsonPath('data.total_payments', 1)
            ->assertJsonPath('data.total_settled_cents', 300);
    });

    it('lists every payment to a platform administrator, without the payment proof', function (): void {
        ghsaMpActAs(ghsaMpAdmin());

        $response = $this->getJson('/api/v1/mpp/payments')
            ->assertOk()
            ->assertJsonPath('data.total', 3);

        expect($response->getContent())->not->toContain('spt_secret_token')
            ->and($response->getContent())->not->toContain('payload_hash');

        $this->getJson('/api/v1/mpp/payments/stats')
            ->assertOk()
            ->assertJsonPath('data.total_payments', 3)
            ->assertJsonPath('data.total_settled_cents', 2100);
    });
});

// ---------------------------------------------------------------------------
// C06/C24: x402 payment history (REST and GraphQL)
// ---------------------------------------------------------------------------

describe('C06/C24 x402 payment history', function (): void {
    beforeEach(function (): void {
        $this->owner = ghsaMpTeamUser();
        $this->ownPayment = ghsaMpX402Payment(ghsaMpTeamId($this->owner), '250000');
        $this->otherPayment = ghsaMpX402Payment(ghsaMpTeamId(ghsaMpTeamUser()), '500000');
        // Inbound payments are recorded without a team.
        $this->platformPayment = ghsaMpX402Payment(null, '750000');
    });

    it('returns nothing over REST to a caller without a team', function (): void {
        ghsaMpActAs(ghsaMpTeamlessUser());

        $this->getJson('/api/v1/x402/payments')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', []);
        $this->getJson("/api/v1/x402/payments/{$this->platformPayment->id}")->assertNotFound();
        $this->getJson("/api/v1/x402/payments/{$this->ownPayment->id}")->assertNotFound();
        $this->getJson('/api/v1/x402/payments/stats?period=month')
            ->assertOk()
            ->assertJsonPath('data.total_payments', 0)
            ->assertJsonPath('data.total_volume_atomic', '0')
            ->assertJsonPath('data.unique_payers', 0);

        expect(X402Payment::query()->count())->toBe(3);
    });

    it('returns nothing over GraphQL to a caller without a team', function (): void {
        $json = ghsaMpGraphql($this, ghsaMpTeamlessUser(), '
            query($id: ID!) {
                x402Payments(first: 100) { id }
                x402Payment(id: $id) { id }
                x402PaymentStats(period: "month") { total_payments total_volume_atomic unique_payers }
            }
        ', ['id' => $this->platformPayment->id]);

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['x402Payments'])->toBe([])
            ->and($json['data']['x402Payment'])->toBeNull()
            ->and($json['data']['x402PaymentStats']['total_payments'])->toBe(0)
            ->and($json['data']['x402PaymentStats']['total_volume_atomic'])->toBe('0');
    });

    it('returns the caller\'s team payments over REST and GraphQL', function (): void {
        ghsaMpActAs($this->owner);

        $this->getJson('/api/v1/x402/payments')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $this->ownPayment->id);
        $this->getJson("/api/v1/x402/payments/{$this->ownPayment->id}")->assertOk();
        $this->getJson("/api/v1/x402/payments/{$this->platformPayment->id}")->assertNotFound();
        $this->getJson('/api/v1/x402/payments/stats?period=month')
            ->assertOk()
            ->assertJsonPath('data.total_payments', 1)
            ->assertJsonPath('data.total_volume_atomic', '250000');

        $json = ghsaMpGraphql($this, $this->owner, '
            query($id: ID!) {
                x402Payments(first: 100) { id }
                x402Payment(id: $id) { id }
                x402PaymentStats(period: "month") { total_payments }
            }
        ', ['id' => $this->ownPayment->id]);

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['x402Payments'])->toBe([['id' => $this->ownPayment->id]])
            ->and($json['data']['x402Payment']['id'])->toBe($this->ownPayment->id)
            ->and($json['data']['x402PaymentStats']['total_payments'])->toBe(1);
    });

    it('returns every payment to a platform administrator', function (): void {
        $admin = ghsaMpAdmin();
        ghsaMpActAs($admin);

        $this->getJson('/api/v1/x402/payments')->assertOk()->assertJsonPath('meta.total', 3);
        $this->getJson("/api/v1/x402/payments/{$this->platformPayment->id}")->assertOk();
        $this->getJson('/api/v1/x402/payments/stats?period=month')
            ->assertOk()
            ->assertJsonPath('data.total_payments', 3)
            ->assertJsonPath('data.total_volume_atomic', '1500000');

        $json = ghsaMpGraphql($this, $admin, '{ x402Payments(first: 100) { id } x402PaymentStats(period: "month") { total_payments } }');

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['x402Payments'])->toHaveCount(3)
            ->and($json['data']['x402PaymentStats']['total_payments'])->toBe(3);
    });
});

// ---------------------------------------------------------------------------
// C24/C25: monetized endpoints
// ---------------------------------------------------------------------------

describe('C24/C25 monetized endpoints', function (): void {
    beforeEach(function (): void {
        $this->owner = ghsaMpTeamUser();
        $this->ownEndpoint = ghsaMpEndpoint(ghsaMpTeamId($this->owner), ['path' => '/api/v1/own']);
        // A price row written by a regular user of another team before the
        // admin-only rule, and a platform row written without a team.
        $this->foreignEndpoint = ghsaMpEndpoint(ghsaMpTeamId(ghsaMpTeamUser()), ['path' => '/api/v1/sms/send', 'method' => 'POST', 'price' => '0.000001']);
        $this->platformEndpoint = ghsaMpEndpoint(null, ['path' => '/api/v1/platform']);
    });

    it('shows no monetized endpoints to a caller without a team', function (): void {
        $user = ghsaMpTeamlessUser();
        ghsaMpActAs($user);

        $this->getJson('/api/v1/x402/endpoints')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/v1/x402/endpoints/{$this->platformEndpoint->id}")->assertNotFound();

        $json = ghsaMpGraphql($this, $user, '
            query($id: ID!) { x402MonetizedEndpoints(first: 100) { id } x402MonetizedEndpoint(id: $id) { id } }
        ', ['id' => $this->platformEndpoint->id]);

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['x402MonetizedEndpoints'])->toBe([])
            ->and($json['data']['x402MonetizedEndpoint'])->toBeNull();
    });

    it('shows a regular team member only the team\'s endpoints', function (): void {
        ghsaMpActAs($this->owner);

        $this->getJson('/api/v1/x402/endpoints')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $this->ownEndpoint->id)
            ->assertJsonPath('data.0.teamId', ghsaMpTeamId($this->owner));
        $this->getJson("/api/v1/x402/endpoints/{$this->foreignEndpoint->id}")->assertNotFound();
    });

    it('still refuses endpoint writes to a regular user, leaving the rows unchanged', function (): void {
        ghsaMpActAs($this->owner);

        $this->putJson("/api/v1/x402/endpoints/{$this->foreignEndpoint->id}", ['price' => '0.5'])->assertForbidden();
        $this->deleteJson("/api/v1/x402/endpoints/{$this->foreignEndpoint->id}")->assertForbidden();

        $json = ghsaMpGraphql($this, $this->owner, 'mutation($id: ID!) { deleteX402MonetizedEndpoint(id: $id) }', ['id' => $this->foreignEndpoint->id]);
        expect($json['errors'][0]['message'] ?? null)->toBe('You are not authorized to manage monetized endpoints.');

        expect($this->foreignEndpoint->fresh()?->price)->toBe('0.000001')
            ->and(X402MonetizedEndpoint::query()->count())->toBe(3);
    });

    it('shows an administrator every endpoint over REST and GraphQL', function (): void {
        $admin = ghsaMpAdmin();
        ghsaMpActAs($admin);

        $response = $this->getJson('/api/v1/x402/endpoints')->assertOk()->assertJsonPath('meta.total', 3);
        expect(collect($response->json('data'))->pluck('id')->all())
            ->toContain($this->foreignEndpoint->id, $this->platformEndpoint->id, $this->ownEndpoint->id);
        $this->getJson("/api/v1/x402/endpoints/{$this->foreignEndpoint->id}")
            ->assertOk()
            ->assertJsonPath('data.price', '0.000001');

        $json = ghsaMpGraphql($this, $admin, '
            query($id: ID!) { x402MonetizedEndpoints(first: 100) { id team_id } x402MonetizedEndpoint(id: $id) { id } }
        ', ['id' => $this->platformEndpoint->id]);

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['x402MonetizedEndpoints'])->toHaveCount(3)
            ->and($json['data']['x402MonetizedEndpoint']['id'])->toBe($this->platformEndpoint->id);
    });

    it('lets an administrator update and remove another team\'s endpoint over REST', function (): void {
        ghsaMpActAs(ghsaMpAdmin());
        Cache::put('x402:route:POST:/api/v1/sms/send', 'stale', 60);

        $this->putJson("/api/v1/x402/endpoints/{$this->foreignEndpoint->id}", ['price' => '0.05'])
            ->assertOk()
            ->assertJsonPath('data.price', '0.05');
        expect(Cache::has('x402:route:POST:/api/v1/sms/send'))->toBeFalse();

        $this->deleteJson("/api/v1/x402/endpoints/{$this->platformEndpoint->id}")->assertNoContent();

        expect(X402MonetizedEndpoint::query()->whereKey($this->platformEndpoint->id)->exists())->toBeFalse();
    });

    it('lets an administrator update and remove another team\'s endpoint over GraphQL', function (): void {
        $admin = ghsaMpAdmin();

        $json = ghsaMpGraphql($this, $admin, '
            mutation($id: ID!) { updateX402MonetizedEndpoint(id: $id, input: {price: "0.05"}) { id price } }
        ', ['id' => $this->foreignEndpoint->id]);
        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['updateX402MonetizedEndpoint']['price'])->toBe('0.05');

        $json = ghsaMpGraphql($this, $admin, 'mutation($id: ID!) { deleteX402MonetizedEndpoint(id: $id) }', ['id' => $this->platformEndpoint->id]);
        expect($json)->not->toHaveKey('errors')
            ->and(X402MonetizedEndpoint::query()->whereKey($this->platformEndpoint->id)->exists())->toBeFalse();
    });

    it('prices a route from its oldest active row, whatever the team', function (): void {
        config(['x402.enabled' => true]);
        // The newer row is inserted first and has the lower team id, so
        // neither insertion order nor the (method, path, team_id) index
        // would pick the older row by accident.
        $newerTeamId = ghsaMpTeamId($this->owner);
        $olderTeamId = ghsaMpTeamId(ghsaMpAdmin());
        ghsaMpEndpoint($newerTeamId, ['method' => 'GET', 'path' => '/api/v1/data', 'price' => '0.000001']);
        $older = ghsaMpEndpoint($olderTeamId, ['method' => 'GET', 'path' => '/api/v1/data', 'price' => '0.25']);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        expect($olderTeamId)->toBeGreaterThan($newerTeamId);

        $config = app(X402PricingService::class)->getRouteConfig(Request::create('/api/v1/data', 'GET'));

        expect($config?->price)->toBe('0.25');
    });
});

// ---------------------------------------------------------------------------
// Review M1: prices are the administrators' (creator recorded, one active row
// per route, legacy rows without a creator deactivated on upgrade)
// ---------------------------------------------------------------------------

const GHSA_MP_DUPLICATE_ROUTE = 'This method and path already has an active price. Update that price, or deactivate it first.';

describe('Review M1 monetized endpoint prices belong to administrators', function (): void {
    it('records the administrator who creates a price over REST and GraphQL', function (): void {
        $admin = ghsaMpAdmin();
        ghsaMpActAs($admin);

        $id = $this->postJson('/api/v1/x402/endpoints', ['method' => 'GET', 'path' => '/api/v1/m1/rest', 'price' => '0.01'])
            ->assertCreated()
            ->json('data.id');
        $json = ghsaMpGraphql($this, $admin, '
            mutation { createX402MonetizedEndpoint(input: {method: "GET", path: "/api/v1/m1/graphql", price: "0.02"}) { id } }
        ');

        expect($json)->not->toHaveKey('errors')
            ->and(X402MonetizedEndpoint::query()->whereKey($id)->first()?->created_by_user_id)->toBe($admin->id)
            ->and(X402MonetizedEndpoint::query()->whereKey($json['data']['createX402MonetizedEndpoint']['id'])->first()?->created_by_user_id)->toBe($admin->id);
    });

    it('refuses a second active price for the same method and path, whatever the team', function (): void {
        $legacy = ghsaMpEndpoint(ghsaMpTeamId(ghsaMpTeamUser()), ['method' => 'POST', 'path' => '/api/v1/sms/send', 'price' => '0.000001']);
        $admin = ghsaMpAdmin();
        ghsaMpActAs($admin);

        $this->postJson('/api/v1/x402/endpoints', ['method' => 'POST', 'path' => '/api/v1/sms/send', 'price' => '0.05'])
            ->assertStatus(422)
            ->assertJsonPath('errors.path.0', GHSA_MP_DUPLICATE_ROUTE);
        $json = ghsaMpGraphql($this, $admin, '
            mutation { createX402MonetizedEndpoint(input: {method: "POST", path: "/api/v1/sms/send", price: "0.05"}) { id } }
        ');

        expect($json['errors'][0]['message'] ?? null)->toBe(GHSA_MP_DUPLICATE_ROUTE)
            ->and(X402MonetizedEndpoint::query()->forRoute('POST', '/api/v1/sms/send')->pluck('id')->all())->toBe([$legacy->id]);
    });

    it('lets an administrator add an inactive price for a priced route, but not activate it while another is active', function (): void {
        ghsaMpEndpoint(null, ['method' => 'GET', 'path' => '/api/v1/m1/dup', 'price' => '0.10']);
        $admin = ghsaMpAdmin();
        ghsaMpActAs($admin);

        $id = $this->postJson('/api/v1/x402/endpoints', ['method' => 'GET', 'path' => '/api/v1/m1/dup', 'price' => '0.20', 'is_active' => false])
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/api/v1/x402/endpoints/{$id}", ['is_active' => true])
            ->assertStatus(422)
            ->assertJsonPath('errors.is_active.0', GHSA_MP_DUPLICATE_ROUTE);
        $json = ghsaMpGraphql($this, $admin, '
            mutation($id: ID!) { updateX402MonetizedEndpoint(id: $id, input: {is_active: true}) { id } }
        ', ['id' => $id]);

        expect($json['errors'][0]['message'] ?? null)->toBe(GHSA_MP_DUPLICATE_ROUTE)
            ->and(X402MonetizedEndpoint::query()->whereKey($id)->first()?->is_active)->toBeFalse();
    });

    it('prices a route from an administrator\'s row before an older row without a creator', function (): void {
        config(['x402.enabled' => true]);
        $admin = ghsaMpAdmin();
        $legacy = ghsaMpEndpoint(null, ['method' => 'GET', 'path' => '/api/v1/m1/priced', 'price' => '0.000001']);
        $legacy->forceFill(['created_at' => now()->subDay()])->save();
        $adminRow = ghsaMpEndpoint(ghsaMpTeamId($admin), ['method' => 'GET', 'path' => '/api/v1/m1/priced', 'price' => '0.25']);
        $adminRow->forceFill(['created_by_user_id' => $admin->id])->save();

        $config = app(X402PricingService::class)->getRouteConfig(Request::create('/api/v1/m1/priced', 'GET'));

        expect($config?->price)->toBe('0.25');
    });

    it('records the administrator who updates a price that has no creator', function (string $via): void {
        config(['x402.enabled' => true]);
        $legacy = ghsaMpEndpoint(null, ['method' => 'GET', 'path' => '/api/v1/m1/legacy', 'price' => '0.30', 'is_active' => false]);
        $admin = ghsaMpAdmin();

        if ($via === 'REST') {
            ghsaMpActAs($admin);
            $this->putJson("/api/v1/x402/endpoints/{$legacy->id}", ['is_active' => true])->assertOk();
        } else {
            $json = ghsaMpGraphql($this, $admin, '
                mutation($id: ID!) { updateX402MonetizedEndpoint(id: $id, input: {is_active: true}) { id } }
            ', ['id' => $legacy->id]);
            expect($json)->not->toHaveKey('errors');
        }

        expect($legacy->fresh()?->created_by_user_id)->toBe($admin->id)
            ->and(app(X402PricingService::class)->getRouteConfig(Request::create('/api/v1/m1/legacy', 'GET'))?->price)->toBe('0.30');
    })->with(['REST', 'GraphQL']);

    it('deactivates the price rows without a creator on upgrade and keeps the administrators\' rows', function (): void {
        $admin = ghsaMpAdmin();
        $legacy = ghsaMpEndpoint(ghsaMpTeamId(ghsaMpTeamUser()), ['method' => 'POST', 'path' => '/api/v1/sms/send', 'price' => '0.000001']);
        $platform = ghsaMpEndpoint(null, ['path' => '/api/v1/m1/platform']);
        $adminRow = ghsaMpEndpoint(ghsaMpTeamId($admin), ['path' => '/api/v1/m1/admin']);
        $adminRow->forceFill(['created_by_user_id' => $admin->id])->save();

        $migration = require base_path('database/migrations/2026_11_19_000001_add_created_by_user_id_to_x402_monetized_endpoints_table.php');
        $migration->up();

        expect($legacy->fresh()?->is_active)->toBeFalse()
            ->and($platform->fresh()?->is_active)->toBeFalse()
            ->and($adminRow->fresh()?->is_active)->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// C36: spending limits of callers without a team
// ---------------------------------------------------------------------------

describe('C36 spending limits without a team', function (): void {
    it('keeps one teamless user\'s MPP limit out of another\'s reach', function (): void {
        $alice = ghsaMpTeamlessUser();
        $bob = ghsaMpTeamlessUser();
        $aliceLimit = ghsaMpMppLimit('agent-x', null, (int) $alice->id);

        ghsaMpActAs($bob);
        expect($this->getJson('/api/v1/mpp/spending-limits')->assertOk()->json('data'))->toBe([]);
        $this->postJson('/api/v1/mpp/spending-limits', ['agent_id' => 'agent-x', 'daily_limit' => 100000000, 'per_tx_limit' => 100000000])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $bob->id);
        $this->deleteJson('/api/v1/mpp/spending-limits/agent-x')->assertOk();

        expect($aliceLimit->fresh()?->daily_limit)->toBe(5000)
            ->and($aliceLimit->fresh()?->per_tx_limit)->toBe(100)
            ->and(MppSpendingLimit::query()->where('agent_id', 'agent-x')->count())->toBe(1);
    });

    it('lets a teamless user manage their own MPP limit', function (): void {
        $alice = ghsaMpTeamlessUser();
        ghsaMpActAs($alice);

        $this->postJson('/api/v1/mpp/spending-limits', ['agent_id' => 'agent-x', 'daily_limit' => 7000, 'per_tx_limit' => 50])
            ->assertCreated();
        $this->postJson('/api/v1/mpp/spending-limits', ['agent_id' => 'agent-x', 'daily_limit' => 8000, 'per_tx_limit' => 50])
            ->assertCreated();

        $row = MppSpendingLimit::query()->where('agent_id', 'agent-x')->sole();
        expect($row->team_id)->toBeNull()
            ->and($row->user_id)->toBe($alice->id)
            ->and($row->daily_limit)->toBe(8000)
            ->and($this->getJson('/api/v1/mpp/spending-limits')->assertOk()->json('data.0.agent_id'))->toBe('agent-x');

        $this->deleteJson('/api/v1/mpp/spending-limits/agent-x')->assertOk();
        expect(MppSpendingLimit::query()->where('agent_id', 'agent-x')->exists())->toBeFalse();
    });

    it('keeps one teamless user\'s x402 limit out of another\'s reach over REST', function (): void {
        $alice = ghsaMpTeamlessUser();
        $bob = ghsaMpTeamlessUser();
        $aliceLimit = ghsaMpX402Limit('agent-x', null, (int) $alice->id);

        ghsaMpActAs($bob);
        $this->getJson('/api/v1/x402/spending-limits')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/x402/spending-limits/agent-x')->assertNotFound();
        $this->putJson('/api/v1/x402/spending-limits/agent-x', ['daily_limit' => '999999999'])->assertNotFound();
        $this->deleteJson('/api/v1/x402/spending-limits/agent-x')->assertNotFound();
        $this->postJson('/api/v1/x402/spending-limits', ['agent_id' => 'agent-x', 'daily_limit' => '999999999'])
            ->assertCreated();

        expect($aliceLimit->fresh()?->daily_limit)->toBe('1000')
            ->and(X402SpendingLimit::query()->where('agent_id', 'agent-x')->where('user_id', $bob->id)->value('daily_limit'))->toBe('999999999');
    });

    it('keeps one teamless user\'s x402 limit out of another\'s reach over GraphQL', function (): void {
        $alice = ghsaMpTeamlessUser();
        $bob = ghsaMpTeamlessUser();
        $aliceLimit = ghsaMpX402Limit('agent-x', null, (int) $alice->id);

        $json = ghsaMpGraphql($this, $bob, '{ x402SpendingLimits(first: 100) { agent_id } x402SpendingLimit(agent_id: "agent-x") { agent_id } }');
        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['x402SpendingLimits'])->toBe([])
            ->and($json['data']['x402SpendingLimit'])->toBeNull();

        $json = ghsaMpGraphql($this, $bob, 'mutation { deleteX402SpendingLimit(agent_id: "agent-x") }');
        expect($json)->toHaveKey('errors');

        $json = ghsaMpGraphql($this, $bob, 'mutation { setX402SpendingLimit(input: {agent_id: "agent-x", daily_limit: "999999999"}) { agent_id daily_limit } }');
        expect($json)->not->toHaveKey('errors');

        expect($aliceLimit->fresh()?->daily_limit)->toBe('1000')
            ->and(X402SpendingLimit::query()->where('agent_id', 'agent-x')->count())->toBe(2);
    });

    it('lets a teamless user manage their own x402 limit over REST and GraphQL', function (): void {
        $alice = ghsaMpTeamlessUser();
        ghsaMpActAs($alice);

        $this->postJson('/api/v1/x402/spending-limits', ['agent_id' => 'agent-x', 'daily_limit' => '5000'])->assertCreated();
        $this->putJson('/api/v1/x402/spending-limits/agent-x', ['daily_limit' => '6000'])
            ->assertOk()
            ->assertJsonPath('data.dailyLimit', '6000');
        $this->getJson('/api/v1/x402/spending-limits/agent-x')->assertOk();

        $json = ghsaMpGraphql($this, $alice, 'mutation { setX402SpendingLimit(input: {agent_id: "agent-x", daily_limit: "7000"}) { daily_limit } }');
        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['setX402SpendingLimit']['daily_limit'])->toBe('7000');

        $row = X402SpendingLimit::query()->where('agent_id', 'agent-x')->sole();
        expect($row->team_id)->toBeNull()
            ->and((int) $row->user_id)->toBe($alice->id);

        $json = ghsaMpGraphql($this, $alice, 'mutation { deleteX402SpendingLimit(agent_id: "agent-x") }');
        expect($json)->not->toHaveKey('errors')
            ->and(X402SpendingLimit::query()->where('agent_id', 'agent-x')->exists())->toBeFalse();
    });

    it('enforces a teamless caller\'s own x402 limit and not another teamless user\'s', function (): void {
        config([
            'x402.client.max_auto_pay_amount'            => '100000',
            'x402.agent_spending.require_approval_above' => '1000000',
        ]);
        /** @var X402SignerInterface&MockInterface $signer */
        $signer = Mockery::mock(X402SignerInterface::class);
        $signer->shouldReceive('signTransferAuthorization')->andReturn([
            'signature'     => '0xsig',
            'authorization' => ['from' => '0xClient', 'to' => '0xRecipient', 'value' => '5000', 'validAfter' => '0', 'validBefore' => (string) (time() + 60), 'nonce' => '0xnonce'],
        ]);
        $signer->shouldReceive('getAddress')->andReturn('0xClient');
        $client = new X402ClientService($signer, new X402HeaderCodecService());
        $header = (new PaymentRequired(
            x402Version: 2,
            resource: new ResourceInfo(url: '/api/v1/premium/data', description: 'Premium data', mimeType: 'application/json'),
            accepts: [new PaymentRequirements(scheme: 'exact', network: 'eip155:8453', asset: '0xUSDC', amount: '5000', payTo: '0xRecipient', maxTimeoutSeconds: 60)],
        ))->toBase64();

        $alice = ghsaMpTeamlessUser();
        $bob = ghsaMpTeamlessUser();
        // Alice's strict limit must not apply to Bob, and Bob must not spend
        // from it.
        $aliceLimit = ghsaMpX402Limit('agent-x', null, (int) $alice->id, ['daily_limit' => '1000']);

        $this->actingAs($alice);
        expect(fn () => $client->handlePaymentRequired($header, 'agent-x'))
            ->toThrow(RuntimeException::class, 'would exceed the daily limit');

        $this->actingAs($bob);
        expect($client->handlePaymentRequired($header, 'agent-x'))->toHaveKey('PAYMENT-SIGNATURE')
            ->and($aliceLimit->fresh()?->spent_today)->toBe('0');
    });

    it('enforces a teamless caller\'s own MPP limit and not another teamless user\'s', function (): void {
        $client = ghsaMpMppClient();
        $alice = ghsaMpTeamlessUser();
        $bob = ghsaMpTeamlessUser();
        $aliceLimit = ghsaMpMppLimit('agent-x', null, (int) $alice->id, ['daily_limit' => 40]);

        $this->actingAs($bob);
        $client->handlePaymentRequired(ghsaMpMppHeader(), 'agent-x');

        $bobLimit = MppSpendingLimit::query()->where('agent_id', 'agent-x')->where('user_id', $bob->id)->sole();
        expect($bobLimit->team_id)->toBeNull()
            ->and($bobLimit->spent_today)->toBe(50)
            ->and($aliceLimit->fresh()?->spent_today)->toBe(0);
    });
});

function ghsaMpMppClient(): MppClientService
{
    config([
        'machinepay.client.enabled'                               => true,
        'machinepay.client.preferred_rails'                       => ['stripe'],
        'machinepay.agent_spending.default_daily_limit'           => 5000,
        'machinepay.agent_spending.default_per_transaction_limit' => 100,
    ]);

    /** @var MppRailResolverService&MockInterface $resolver */
    $resolver = Mockery::mock(MppRailResolverService::class);
    $resolver->shouldReceive('isRailAvailable')->andReturn(true);

    return new MppClientService($resolver);
}

function ghsaMpMppHeader(): string
{
    return 'Payment ' . (new MppChallenge(
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
}

// ---------------------------------------------------------------------------
// D08: MPP agent ids are unique per team
// ---------------------------------------------------------------------------

describe('D08 MPP agent ids per team', function (): void {
    it('does not let one team block another team\'s agent id over REST', function (): void {
        $squatter = ghsaMpTeamUser();
        $victim = ghsaMpTeamUser();

        ghsaMpActAs($squatter);
        $this->postJson('/api/v1/mpp/spending-limits', ['agent_id' => 'agent-x', 'daily_limit' => 0, 'per_tx_limit' => 0])
            ->assertCreated();

        ghsaMpActAs($victim);
        $this->postJson('/api/v1/mpp/spending-limits', ['agent_id' => 'agent-x', 'daily_limit' => 9000, 'per_tx_limit' => 200])
            ->assertCreated()
            ->assertJsonPath('data.team_id', ghsaMpTeamId($victim));
        expect($this->getJson('/api/v1/mpp/spending-limits')->assertOk()->json('data'))->toHaveCount(1);

        $squatterRow = MppSpendingLimit::query()->where('agent_id', 'agent-x')->where('team_id', ghsaMpTeamId($squatter))->sole();
        expect($squatterRow->daily_limit)->toBe(0)
            ->and(MppSpendingLimit::query()->where('agent_id', 'agent-x')->count())->toBe(2);

        // Deleting removes only the caller's own row.
        $this->deleteJson('/api/v1/mpp/spending-limits/agent-x')->assertOk();
        expect($squatterRow->fresh())->not->toBeNull();
    });

    it('uses the caller\'s own limit when another team holds the same agent id', function (): void {
        $client = ghsaMpMppClient();
        $squatter = ghsaMpTeamUser();
        $caller = ghsaMpTeamUser();
        $squatterLimit = ghsaMpMppLimit('agent-x', ghsaMpTeamId($squatter), null, ['daily_limit' => 0, 'per_tx_limit' => 0]);

        $this->actingAs($caller);
        $client->handlePaymentRequired(ghsaMpMppHeader(), 'agent-x');

        $own = MppSpendingLimit::query()->where('agent_id', 'agent-x')->where('team_id', ghsaMpTeamId($caller))->sole();
        expect($own->spent_today)->toBe(50)
            ->and($squatterLimit->fresh()?->spent_today)->toBe(0);
    });
});
