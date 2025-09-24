<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol\Api;

use App\Domain\AgentProtocol\Aggregates\AgentIdentityAggregate;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected string $senderDid;

    protected string $receiverDid;

    protected string $senderAgentId;

    protected string $receiverAgentId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sender agent
        $this->senderAgentId = Str::uuid()->toString();
        $this->senderDid = 'did:example:sender123';
        $senderAggregate = AgentIdentityAggregate::register(
            agentId: $this->senderAgentId,
            did: $this->senderDid,
            name: 'Sender Agent',
            type: 'autonomous'
        );
        $senderAggregate->createWallet(
            walletId: Str::uuid()->toString(),
            currency: 'USD',
            initialBalance: 1000.00
        );
        $senderAggregate->persist();

        // Create Agent model for sender
        Agent::create([
            'agent_id' => $this->senderAgentId,
            'did'      => $this->senderDid,
            'name'     => 'Sender Agent',
            'type'     => 'autonomous',
            'status'   => 'active',
        ]);

        // Create receiver agent
        $this->receiverAgentId = Str::uuid()->toString();
        $this->receiverDid = 'did:example:receiver456';
        $receiverAggregate = AgentIdentityAggregate::register(
            agentId: $this->receiverAgentId,
            did: $this->receiverDid,
            name: 'Receiver Agent',
            type: 'autonomous'
        );
        $receiverAggregate->createWallet(
            walletId: Str::uuid()->toString(),
            currency: 'USD',
            initialBalance: 0.00
        );
        $receiverAggregate->persist();

        // Create Agent model for receiver
        Agent::create([
            'agent_id' => $this->receiverAgentId,
            'did'      => $this->receiverDid,
            'name'     => 'Receiver Agent',
            'type'     => 'autonomous',
            'status'   => 'active',
        ]);
    }

    public function test_can_initiate_payment_between_agents(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/agents/{$this->senderDid}/payments", [
            'receiver_did' => $this->receiverDid,
            'amount'       => 100.00,
            'currency'     => 'USD',
            'description'  => 'Test payment',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'transaction_id',
                'status',
                'sender_did',
                'receiver_did',
                'amount',
                'currency',
                'created_at',
            ])
            ->assertJson([
                'status'       => 'pending',
                'sender_did'   => $this->senderDid,
                'receiver_did' => $this->receiverDid,
                'amount'       => 100.00,
                'currency'     => 'USD',
            ]);
    }

    public function test_can_get_payment_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a payment first
        $createResponse = $this->postJson("/api/agents/{$this->senderDid}/payments", [
            'receiver_did' => $this->receiverDid,
            'amount'       => 50.00,
            'currency'     => 'USD',
        ]);

        $transactionId = $createResponse->json('transaction_id');

        // Get payment status
        $response = $this->getJson("/api/agents/{$this->senderDid}/payments/{$transactionId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'transaction_id',
                'status',
                'sender_agent_id',
                'receiver_agent_id',
                'amount',
                'currency',
                'created_at',
                'updated_at',
            ]);
    }

    public function test_can_confirm_payment(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a payment
        $createResponse = $this->postJson("/api/agents/{$this->senderDid}/payments", [
            'receiver_did' => $this->receiverDid,
            'amount'       => 75.00,
            'currency'     => 'USD',
        ]);

        $transactionId = $createResponse->json('transaction_id');

        // Confirm the payment as receiver
        $response = $this->postJson("/api/agents/{$this->receiverDid}/payments/{$transactionId}/confirm", [
            'confirmation_code' => 'TEST123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'transaction_id' => $transactionId,
                'status'         => 'confirmed',
            ]);
    }

    public function test_can_cancel_payment(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a payment
        $createResponse = $this->postJson("/api/agents/{$this->senderDid}/payments", [
            'receiver_did' => $this->receiverDid,
            'amount'       => 25.00,
            'currency'     => 'USD',
        ]);

        $transactionId = $createResponse->json('transaction_id');

        // Cancel the payment as sender
        $response = $this->postJson("/api/agents/{$this->senderDid}/payments/{$transactionId}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'transaction_id' => $transactionId,
                'status'         => 'cancelled',
            ]);
    }

    public function test_cannot_initiate_payment_without_authentication(): void
    {
        $response = $this->postJson("/api/agents/{$this->senderDid}/payments", [
            'receiver_did' => $this->receiverDid,
            'amount'       => 100.00,
        ]);

        $response->assertStatus(401);
    }

    public function test_validates_payment_amount(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/agents/{$this->senderDid}/payments", [
            'receiver_did' => $this->receiverDid,
            'amount'       => -10.00, // Invalid negative amount
            'currency'     => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_handles_invalid_receiver_did(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/agents/{$this->senderDid}/payments", [
            'receiver_did' => 'did:invalid:nonexistent',
            'amount'       => 100.00,
            'currency'     => 'USD',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Receiver agent not found',
            ]);
    }

    public function test_supports_split_payments(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/agents/{$this->senderDid}/payments", [
            'receiver_did'   => $this->receiverDid,
            'amount'         => 100.00,
            'currency'       => 'USD',
            'split_payments' => [
                ['agent_did' => 'did:example:split1', 'amount' => 20.00],
                ['agent_did' => 'did:example:split2', 'amount' => 30.00],
            ],
        ]);

        $response->assertStatus(201);
    }

    public function test_only_receiver_can_confirm_payment(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a payment
        $createResponse = $this->postJson("/api/agents/{$this->senderDid}/payments", [
            'receiver_did' => $this->receiverDid,
            'amount'       => 50.00,
            'currency'     => 'USD',
        ]);

        $transactionId = $createResponse->json('transaction_id');

        // Try to confirm as sender (should fail)
        $response = $this->postJson("/api/agents/{$this->senderDid}/payments/{$transactionId}/confirm", [
            'confirmation_code' => 'TEST123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Only receiver can confirm payment',
            ]);
    }

    public function test_only_sender_can_cancel_payment(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a payment
        $createResponse = $this->postJson("/api/agents/{$this->senderDid}/payments", [
            'receiver_did' => $this->receiverDid,
            'amount'       => 50.00,
            'currency'     => 'USD',
        ]);

        $transactionId = $createResponse->json('transaction_id');

        // Try to cancel as receiver (should fail)
        $response = $this->postJson("/api/agents/{$this->receiverDid}/payments/{$transactionId}/cancel");

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Only sender can cancel payment',
            ]);
    }
}
