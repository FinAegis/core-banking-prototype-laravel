<?php

declare(strict_types=1);

namespace Tests\Feature\Api\X402;

use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Domain\X402\Models\X402Payment;
use App\Domain\X402\Models\X402SpendingLimit;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
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

    // ----------------------------------------------------------------
    // Endpoint Mutations — Team Scoping
    // ----------------------------------------------------------------

    public function test_create_endpoint_assigns_team_id(): void
    {
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

    public function test_update_endpoint_rejects_cross_tenant(): void
    {
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

        // Should get an error (model not found for this team)
        $response->assertOk();
        $this->assertNotNull($response->json('errors'));
    }

    public function test_delete_endpoint_rejects_cross_tenant(): void
    {
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
        $this->assertNotNull($response->json('errors'));

        // Endpoint should still exist
        $this->assertDatabaseHas('x402_monetized_endpoints', ['id' => $otherEndpoint->id]);
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
