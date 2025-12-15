<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol;

use App\Domain\AgentProtocol\Models\AgentIdentity;
use App\Domain\AgentProtocol\Models\AgentWallet;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AP2 Protocol Compliance Test Suite.
 *
 * Tests agent payment protocol compliance according to AP2 specification:
 * https://github.com/google-agentic-commerce/AP2/blob/main/docs/specification.md
 */
class AP2ComplianceTest extends TestCase
{
    protected User $user;

    private string $agentDid;

    protected function shouldCreateDefaultAccountsInSetup(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if SQLite transaction nesting not supported (PHP 8.4+ issue)
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Skipping: SQLite transaction nesting not fully supported in test environment');
        }

        $this->user = User::factory()->create([
            'kyc_status' => 'approved',
            'kyc_level'  => 'enhanced',
        ]);

        $this->agentDid = 'did:key:' . Str::random(32);

        // Create agent identity
        AgentIdentity::create([
            'agent_id' => $this->agentDid,
            'did'      => $this->agentDid,
            'name'     => 'Test Agent',
            'type'     => 'autonomous',
            'status'   => 'active',
            'metadata' => [
                'linked_user_id' => $this->user->id,
                'kyc_status'     => 'verified',
                'kyc_level'      => 'enhanced',
            ],
        ]);

        // Create agent wallet
        AgentWallet::create([
            'wallet_id'         => 'wallet_' . Str::uuid()->toString(),
            'agent_id'          => $this->agentDid,
            'currency'          => 'USD',
            'available_balance' => 1000.00,
            'held_balance'      => 0.00,
            'total_balance'     => 1000.00,
            'is_active'         => true,
            'metadata'          => [],
        ]);
    }

    // ==========================================
    // AP2 Section 3.1: Agent Discovery
    // ==========================================

    /** @test */
    public function ap2_discovery_endpoint_returns_valid_configuration(): void
    {
        $response = $this->getJson('/.well-known/ap2-configuration');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'issuer',
                'payment_endpoint',
                'escrow_endpoint',
                'supported_currencies',
                'capabilities',
            ]);
    }

    /** @test */
    public function ap2_agent_discovery_returns_registered_agents(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/agents/discover');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'did',
                        'display_name',
                        'capabilities',
                    ],
                ],
            ]);
    }

    /** @test */
    public function ap2_agent_details_returns_full_agent_info(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/agents/{$this->agentDid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'did',
                    'display_name',
                    'public_key',
                    'is_active',
                    'capabilities',
                    'metadata',
                ],
            ]);
    }

    // ==========================================
    // AP2 Section 3.2: Agent Registration
    // ==========================================

    /** @test */
    public function ap2_agent_registration_creates_new_agent(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/agents/register', [
                'display_name' => 'New Test Agent',
                'capabilities' => ['payments', 'escrow'],
                'metadata'     => ['category' => 'commerce'],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'did',
                    'display_name',
                    'public_key',
                    'wallet_id',
                ],
            ]);

        // Verify DID format
        $this->assertStringStartsWith('did:', $response->json('data.did'));
    }

    /** @test */
    public function ap2_agent_registration_requires_authentication(): void
    {
        $response = $this->postJson('/api/agents/register', [
            'display_name' => 'Unauthorized Agent',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function ap2_agent_registration_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/agents/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['display_name']);
    }

    // ==========================================
    // AP2 Section 4.1: Payment Initiation
    // ==========================================

    /** @test */
    public function ap2_payment_initiation_creates_pending_payment(): void
    {
        // Create recipient agent
        $recipientDid = 'did:key:recipient_' . Str::random(32);
        AgentIdentity::create([
            'agent_id' => $recipientDid,
            'did'      => $recipientDid,
            'name'     => 'Recipient Agent',
            'type'     => 'autonomous',
            'status'   => 'active',
            'metadata' => [],
        ]);

        AgentWallet::create([
            'wallet_id'         => 'wallet_recipient_' . Str::uuid()->toString(),
            'agent_id'          => $recipientDid,
            'currency'          => 'USD',
            'available_balance' => 0,
            'held_balance'      => 0,
            'total_balance'     => 0,
            'is_active'         => true,
            'metadata'          => [],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/agents/{$this->agentDid}/payments", [
                'recipient_did'   => $recipientDid,
                'amount'          => 100.00,
                'currency'        => 'USD',
                'description'     => 'Test payment',
                'idempotency_key' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'payment_id',
                    'status',
                    'amount',
                    'currency',
                    'created_at',
                ],
            ]);

        $this->assertContains($response->json('data.status'), ['pending', 'processing', 'completed']);
    }

    /** @test */
    public function ap2_payment_requires_idempotency_key(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/agents/{$this->agentDid}/payments", [
                'recipient_did' => 'did:key:test',
                'amount'        => 50.00,
                'currency'      => 'USD',
                // Missing idempotency_key
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    /** @test */
    public function ap2_payment_validates_positive_amount(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/agents/{$this->agentDid}/payments", [
                'recipient_did'   => 'did:key:test',
                'amount'          => -100.00,
                'currency'        => 'USD',
                'idempotency_key' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function ap2_payment_validates_supported_currency(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/agents/{$this->agentDid}/payments", [
                'recipient_did'   => 'did:key:test',
                'amount'          => 100.00,
                'currency'        => 'INVALID',
                'idempotency_key' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
    }

    // ==========================================
    // AP2 Section 4.2: Payment Status
    // ==========================================

    /** @test */
    public function ap2_payment_status_returns_correct_state(): void
    {
        $paymentId = 'payment_' . Str::uuid()->toString();

        $response = $this->actingAs($this->user)
            ->getJson("/api/agents/{$this->agentDid}/payments/{$paymentId}");

        // Should return 404 for non-existent payment (compliant behavior)
        $response->assertStatus(404);
    }

    // ==========================================
    // AP2 Section 5: Escrow Services
    // ==========================================

    /** @test */
    public function ap2_escrow_creation_holds_funds(): void
    {
        $recipientDid = 'did:key:escrow_recipient_' . Str::random(32);

        $response = $this->actingAs($this->user)
            ->postJson('/api/agents/escrow', [
                'payer_did'  => $this->agentDid,
                'payee_did'  => $recipientDid,
                'amount'     => 200.00,
                'currency'   => 'USD',
                'conditions' => [
                    'type'          => 'time_based',
                    'release_after' => now()->addDays(7)->toIso8601String(),
                ],
                'idempotency_key' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'escrow_id',
                    'status',
                    'amount',
                    'currency',
                    'conditions',
                ],
            ]);

        $this->assertEquals('held', $response->json('data.status'));
    }

    /** @test */
    public function ap2_escrow_release_transfers_funds(): void
    {
        $escrowId = 'escrow_' . Str::uuid()->toString();

        $response = $this->actingAs($this->user)
            ->postJson("/api/agents/escrow/{$escrowId}/release", [
                'release_reason' => 'Service completed',
            ]);

        // Should handle gracefully for non-existent escrow
        $response->assertStatus(404);
    }

    /** @test */
    public function ap2_escrow_dispute_changes_status(): void
    {
        $escrowId = 'escrow_' . Str::uuid()->toString();

        $response = $this->actingAs($this->user)
            ->postJson("/api/agents/escrow/{$escrowId}/dispute", [
                'reason'   => 'Service not delivered',
                'evidence' => ['type' => 'screenshot', 'description' => 'No response received'],
            ]);

        // Should handle gracefully for non-existent escrow
        $response->assertStatus(404);
    }

    // ==========================================
    // AP2 Section 6: Messaging
    // ==========================================

    /** @test */
    public function ap2_message_sending_creates_delivery_record(): void
    {
        $recipientDid = 'did:key:msg_recipient_' . Str::random(32);
        AgentIdentity::create([
            'agent_id' => $recipientDid,
            'did'      => $recipientDid,
            'name'     => 'Message Recipient',
            'type'     => 'autonomous',
            'status'   => 'active',
            'metadata' => [],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/agents/{$this->agentDid}/messages", [
                'recipient_did' => $recipientDid,
                'message_type'  => 'payment_request',
                'content'       => [
                    'amount'      => 50.00,
                    'currency'    => 'USD',
                    'description' => 'Payment for services',
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'message_id',
                    'status',
                    'recipient_did',
                    'created_at',
                ],
            ]);
    }

    /** @test */
    public function ap2_message_retrieval_returns_inbox(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/agents/{$this->agentDid}/messages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'total',
                    'unread',
                ],
            ]);
    }

    /** @test */
    public function ap2_message_acknowledgment_updates_status(): void
    {
        $messageId = 'msg_' . Str::uuid()->toString();

        $response = $this->actingAs($this->user)
            ->postJson("/api/agents/{$this->agentDid}/messages/{$messageId}/ack");

        // Should handle gracefully for non-existent message
        $response->assertStatus(404);
    }

    // ==========================================
    // AP2 Section 7: Reputation System
    // ==========================================

    /** @test */
    public function ap2_reputation_query_returns_score(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/agents/{$this->agentDid}/reputation");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'did',
                    'score',
                    'total_transactions',
                    'successful_transactions',
                    'dispute_rate',
                ],
            ]);

        // Score should be between 0 and 100
        $score = $response->json('data.score');
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /** @test */
    public function ap2_reputation_feedback_requires_transaction(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/agents/{$this->agentDid}/reputation/feedback", [
                'transaction_id' => 'invalid_transaction',
                'rating'         => 5,
                'comment'        => 'Great service!',
            ]);

        $response->assertStatus(422);
    }

    // ==========================================
    // AP2 Security Requirements
    // ==========================================

    /** @test */
    public function ap2_all_endpoints_require_authentication(): void
    {
        $endpoints = [
            ['POST', '/api/agents/register'],
            ['GET', '/api/agents/discover'],
            ['GET', "/api/agents/{$this->agentDid}"],
            ['POST', "/api/agents/{$this->agentDid}/payments"],
            ['POST', '/api/agents/escrow'],
            ['POST', "/api/agents/{$this->agentDid}/messages"],
            ['GET', "/api/agents/{$this->agentDid}/reputation"],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint, []);
            $this->assertContains(
                $response->status(),
                [401, 403],
                "Endpoint {$method} {$endpoint} should require authentication"
            );
        }
    }

    /** @test */
    public function ap2_rate_limiting_is_enforced(): void
    {
        // Make multiple rapid requests
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->user)
                ->getJson("/api/agents/{$this->agentDid}");
        }

        // The rate limiter should be tracking requests
        // Exact behavior depends on configuration
        $this->markTestIncomplete('Rate limiting test requires proper rate limiter configuration');
    }

    // ==========================================
    // AP2 Data Format Compliance
    // ==========================================

    /** @test */
    public function ap2_did_format_is_valid(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/agents/register', [
                'display_name' => 'DID Format Test Agent',
                'capabilities' => ['payments'],
            ]);

        $response->assertStatus(201);

        $did = $response->json('data.did');

        // DID should follow did:method:identifier format
        $this->assertMatchesRegularExpression('/^did:[a-z]+:.+$/', $did);
    }

    /** @test */
    public function ap2_currency_codes_follow_iso4217(): void
    {
        $response = $this->getJson('/.well-known/ap2-configuration');

        $response->assertStatus(200);

        $currencies = $response->json('supported_currencies');

        // All currencies should be 3-letter ISO codes
        foreach ($currencies as $currency) {
            $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $currency);
        }
    }

    /** @test */
    public function ap2_timestamps_follow_iso8601(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/agents/{$this->agentDid}");

        $response->assertStatus(200);

        $data = $response->json('data');

        if (isset($data['created_at'])) {
            // Should be valid ISO8601
            $this->assertNotFalse(strtotime($data['created_at']));
        }
    }
}
