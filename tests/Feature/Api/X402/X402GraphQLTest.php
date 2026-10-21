<?php

declare(strict_types=1);

namespace Tests\Feature\Api\X402;

use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Domain\X402\Models\X402Payment;
use App\Domain\X402\Models\X402SpendingLimit;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class X402GraphQLTest extends TestCase
{
    protected User $user;

    protected int $teamId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        /** @var \App\Models\Team $team */
        $team = $this->user->currentTeam;
        $this->teamId = (int) $team->id;
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);
    }

    /**
     * Monetized endpoint mutations are limited to administrators
     * (StaffRoles::ADMINS).
     */
    private function makeUserAdmin(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->user->assignRole('admin');
    }

    // ----------------------------------------------------------------
    // Endpoint Mutations — Team Scoping
    // ----------------------------------------------------------------

    public function test_endpoint_mutations_are_refused_without_an_administrator_role(): void
    {
        $endpoint = X402MonetizedEndpoint::create([
            'method'    => 'GET',
            'path'      => '/api/v1/own',
            'price'     => '0.01',
            'network'   => 'eip155:8453',
            'is_active' => true,
            'team_id'   => $this->teamId,
        ]);

        $variables = ['id' => $endpoint->id];
        $mutations = [
            'mutation { createX402MonetizedEndpoint(input: {method: "POST", path: "/api/v1/sms/send", price: "0"}) { id } }',
            'mutation($id: ID!) { updateX402MonetizedEndpoint(id: $id, input: {price: "0"}) { id } }',
            'mutation($id: ID!) { deleteX402MonetizedEndpoint(id: $id) }',
        ];

        foreach ($mutations as $query) {
            $response = $this->postJson('/graphql', ['query' => $query, 'variables' => str_contains($query, '$id') ? $variables : []]);

            $response->assertOk();
            $this->assertSame('You are not authorized to manage monetized endpoints.', $response->json('errors.0.message'));
        }

        $this->assertSame(1, X402MonetizedEndpoint::query()->count());
        $this->assertSame('/api/v1/own', X402MonetizedEndpoint::query()->sole()->path);
        $this->assertDatabaseHas('x402_monetized_endpoints', ['id' => $endpoint->id, 'price' => $endpoint->price]);
    }

    public function test_create_endpoint_assigns_team_id(): void
    {
        $this->makeUserAdmin();

        $response = $this->postJson('/graphql', [
            'query' => '
                mutation {
                    createX402MonetizedEndpoint(input: {
                        method: "GET"
                        path: "/api/v1/test"
                        price: "0.01"
                    }) {
                        id
                        method
                        path
                    }
                }
            ',
        ]);

        $response->assertOk();

        $endpoint = X402MonetizedEndpoint::first();
        $this->assertNotNull($endpoint);
        $this->assertSame($this->teamId, $endpoint->team_id);
    }

    /**
     * Prices apply platform-wide, so administrators manage every row,
     * whichever team created it (GHSA-385w-q6j4-5qw6 C25).
     */
    public function test_admin_updates_endpoint_of_another_team(): void
    {
        $this->makeUserAdmin();

        $otherUser = User::factory()->withPersonalTeam()->create();
        /** @var \App\Models\Team $otherTeam */
        $otherTeam = $otherUser->currentTeam;
        $otherEndpoint = X402MonetizedEndpoint::create([
            'method'    => 'GET',
            'path'      => '/api/v1/other',
            'price'     => '0.01',
            'network'   => 'eip155:8453',
            'is_active' => true,
            'team_id'   => (int) $otherTeam->id,
        ]);

        $response = $this->postJson('/graphql', [
            'query' => '
                mutation($id: ID!) {
                    updateX402MonetizedEndpoint(id: $id, input: {
                        price: "0.99"
                    }) {
                        id
                        price
                    }
                }
            ',
            'variables' => ['id' => $otherEndpoint->id],
        ]);

        $response->assertOk();
        $this->assertNull($response->json('errors'));
        $this->assertSame('0.99', $response->json('data.updateX402MonetizedEndpoint.price'));
        $this->assertDatabaseHas('x402_monetized_endpoints', ['id' => $otherEndpoint->id, 'price' => '0.99']);
    }

    public function test_admin_deletes_endpoint_of_another_team(): void
    {
        $this->makeUserAdmin();

        $otherUser = User::factory()->withPersonalTeam()->create();
        /** @var \App\Models\Team $otherTeam */
        $otherTeam = $otherUser->currentTeam;
        $otherEndpoint = X402MonetizedEndpoint::create([
            'method'    => 'GET',
            'path'      => '/api/v1/other',
            'price'     => '0.01',
            'network'   => 'eip155:8453',
            'is_active' => true,
            'team_id'   => (int) $otherTeam->id,
        ]);

        $response = $this->postJson('/graphql', [
            'query' => '
                mutation($id: ID!) {
                    deleteX402MonetizedEndpoint(id: $id)
                }
            ',
            'variables' => ['id' => $otherEndpoint->id],
        ]);

        $response->assertOk();
        $this->assertNull($response->json('errors'));
        $this->assertDatabaseMissing('x402_monetized_endpoints', ['id' => $otherEndpoint->id]);
    }

    // ----------------------------------------------------------------
    // Spending Limit Mutations — Team Scoping
    // ----------------------------------------------------------------

    public function test_set_spending_limit_assigns_team_id(): void
    {
        $response = $this->postJson('/graphql', [
            'query' => '
                mutation {
                    setX402SpendingLimit(input: {
                        agent_id: "my-agent"
                        daily_limit: "10000000"
                    }) {
                        id
                        agent_id
                        daily_limit
                    }
                }
            ',
        ]);

        $response->assertOk();

        $limit = X402SpendingLimit::first();
        $this->assertNotNull($limit);
        $this->assertSame($this->teamId, $limit->team_id);
    }

    public function test_delete_spending_limit_rejects_cross_tenant(): void
    {
        $otherUser = User::factory()->withPersonalTeam()->create();
        /** @var \App\Models\Team $otherTeam */
        $otherTeam = $otherUser->currentTeam;
        X402SpendingLimit::create([
            'agent_id'         => 'other-agent',
            'agent_type'       => 'ai_agent',
            'daily_limit'      => '10000000',
            'spent_today'      => '0',
            'auto_pay_enabled' => false,
            'limit_resets_at'  => now()->addDay(),
            'team_id'          => (int) $otherTeam->id,
        ]);

        $response = $this->postJson('/graphql', [
            'query' => '
                mutation {
                    deleteX402SpendingLimit(agent_id: "other-agent")
                }
            ',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('errors'));

        // Limit should still exist
        $this->assertDatabaseHas('x402_spending_limits', ['agent_id' => 'other-agent']);
    }

    // ----------------------------------------------------------------
    // Stats Query — Team Isolation
    // ----------------------------------------------------------------

    public function test_stats_query_team_isolation(): void
    {
        $otherUser = User::factory()->withPersonalTeam()->create();
        /** @var \App\Models\Team $otherTeam */
        $otherTeam = $otherUser->currentTeam;

        // Create a payment for a different team
        X402Payment::create([
            'payer_address'   => '0xOther',
            'pay_to_address'  => '0xRecipient',
            'amount'          => '1000000',
            'network'         => 'eip155:8453',
            'asset'           => '0xUSDC',
            'scheme'          => 'exact',
            'status'          => 'settled',
            'endpoint_method' => 'GET',
            'endpoint_path'   => '/api/v1/test',
            'team_id'         => (int) $otherTeam->id,
        ]);

        // Create a payment for our team
        X402Payment::create([
            'payer_address'   => '0xMine',
            'pay_to_address'  => '0xRecipient',
            'amount'          => '500000',
            'network'         => 'eip155:8453',
            'asset'           => '0xUSDC',
            'scheme'          => 'exact',
            'status'          => 'settled',
            'endpoint_method' => 'GET',
            'endpoint_path'   => '/api/v1/test',
            'team_id'         => $this->teamId,
        ]);

        $response = $this->postJson('/graphql', [
            'query' => '
                {
                    x402PaymentStats(period: "month") {
                        total_payments
                        total_settled
                        total_volume_atomic
                    }
                }
            ',
        ]);

        $response->assertOk();

        $data = $response->json('data.x402PaymentStats');
        $this->assertNotNull($data);
        // Should only see our team's payment
        $this->assertSame(1, $data['total_payments']);
        $this->assertSame(1, $data['total_settled']);
        $this->assertSame('500000', $data['total_volume_atomic']);
    }
}
