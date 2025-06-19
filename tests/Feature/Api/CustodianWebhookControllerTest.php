<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CustodianWebhook;
use App\Domain\Custodian\Services\WebhookVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class CustodianWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $verificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        Queue::fake();
        
        $this->verificationService = Mockery::mock(WebhookVerificationService::class);
        $this->app->instance(WebhookVerificationService::class, $this->verificationService);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_receive_paysera_webhook()
    {
        $payload = json_encode([
            'event' => 'account.balance_changed',
            'event_id' => 'evt_123456',
            'account_id' => 'acc_123456',
            'balance' => [
                'currency' => 'EUR',
                'amount' => 150000,
            ],
            'timestamp' => '2025-06-18T12:00:00Z',
        ]);
        
        $signature = 'test-signature';
        
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->once()
            ->with('paysera', $payload, $signature, Mockery::any())
            ->andReturn(true);
        
        $response = $this->postJson('/api/webhooks/custodian/paysera', json_decode($payload, true), [
            'X-Paysera-Signature' => $signature,
        ]);
        
        $response->assertAccepted()
            ->assertJsonFragment(['status' => 'accepted']);
        
        $this->assertDatabaseHas('custodian_webhooks', [
            'custodian_name' => 'paysera',
            'event_type' => 'account.balance_changed',
            'event_id' => 'evt_123456',
            'status' => 'pending',
        ]);
        
        Queue::assertPushed(\App\Jobs\ProcessCustodianWebhook::class);
    }

    /** @test */
    public function it_can_receive_santander_webhook()
    {
        $payload = json_encode([
            'event_type' => 'transaction.created',
            'id' => 'txn_789012',
            'account_number' => '12345678',
            'transaction' => [
                'amount' => 50000,
                'currency' => 'USD',
                'direction' => 'credit',
            ],
            'created_at' => '2025-06-18T12:00:00Z',
        ]);
        
        $signature = 'santander-signature';
        
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->once()
            ->with('santander', $payload, $signature, Mockery::any())
            ->andReturn(true);
        
        $response = $this->postJson('/api/webhooks/custodian/santander', json_decode($payload, true), [
            'X-Santander-Signature' => $signature,
        ]);
        
        $response->assertAccepted()
            ->assertJsonFragment(['status' => 'accepted']);
        
        $this->assertDatabaseHas('custodian_webhooks', [
            'custodian_name' => 'santander',
            'event_type' => 'transaction.created',
            'event_id' => 'txn_789012',
            'status' => 'pending',
        ]);
        
        Queue::assertPushed(\App\Jobs\ProcessCustodianWebhook::class);
    }

    /** @test */
    public function it_can_receive_mock_webhook()
    {
        $payload = json_encode([
            'type' => 'test_event',
            'id' => 'mock_123',
            'data' => [
                'test' => 'value',
            ],
        ]);
        
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->once()
            ->with('mock', $payload, 'mock-signature', Mockery::any())
            ->andReturn(true);
        
        $response = $this->postJson('/api/webhooks/custodian/mock', json_decode($payload, true));
        
        $response->assertAccepted()
            ->assertJsonFragment(['status' => 'accepted']);
        
        $this->assertDatabaseHas('custodian_webhooks', [
            'custodian_name' => 'mock',
            'event_type' => 'test_event',
            'event_id' => 'mock_123',
            'status' => 'pending',
        ]);
        
        Queue::assertPushed(\App\Jobs\ProcessCustodianWebhook::class);
    }

    /** @test */
    public function it_rejects_webhook_with_invalid_signature()
    {
        $payload = json_encode([
            'event' => 'account.balance_changed',
            'event_id' => 'evt_123456',
        ]);
        
        $signature = 'invalid-signature';
        
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->once()
            ->with('paysera', $payload, $signature, Mockery::any())
            ->andReturn(false);
        
        $response = $this->postJson('/api/webhooks/custodian/paysera', json_decode($payload, true), [
            'X-Paysera-Signature' => $signature,
        ]);
        
        $response->assertUnauthorized()
            ->assertJsonFragment(['error' => 'Invalid signature']);
        
        $this->assertDatabaseMissing('custodian_webhooks', [
            'custodian_name' => 'paysera',
            'event_id' => 'evt_123456',
        ]);
        
        Queue::assertNotPushed(\App\Jobs\ProcessCustodianWebhook::class);
    }

    /** @test */
    public function it_rejects_webhook_with_invalid_json_payload()
    {
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);
        
        $response = $this->call('POST', '/api/webhooks/custodian/paysera', [], [], [], [
            'HTTP_X-Paysera-Signature' => 'test-signature',
            'HTTP_Content-Type' => 'application/json',
        ], 'invalid-json{');
        
        $response->assertBadRequest()
            ->assertJsonFragment(['error' => 'Invalid payload']);
        
        Queue::assertNotPushed(\App\Jobs\ProcessCustodianWebhook::class);
    }

    /** @test */
    public function it_handles_duplicate_webhook_gracefully()
    {
        $eventId = 'evt_duplicate';
        
        // Create existing webhook
        CustodianWebhook::create([
            'custodian_name' => 'paysera',
            'event_type' => 'account.balance_changed',
            'event_id' => $eventId,
            'headers' => [],
            'payload' => [],
            'signature' => 'existing-signature',
            'status' => 'processed',
        ]);
        
        $payload = json_encode([
            'event' => 'account.balance_changed',
            'event_id' => $eventId,
            'account_id' => 'acc_123456',
        ]);
        
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);
        
        $response = $this->postJson('/api/webhooks/custodian/paysera', json_decode($payload, true), [
            'X-Paysera-Signature' => 'test-signature',
        ]);
        
        $response->assertAccepted()
            ->assertJsonFragment(['status' => 'accepted', 'duplicate' => true]);
        
        // Should only have one webhook with this event_id
        $this->assertEquals(1, CustodianWebhook::where('event_id', $eventId)->count());
        
        // Should not dispatch another job
        Queue::assertNotPushed(\App\Jobs\ProcessCustodianWebhook::class);
    }

    /** @test */
    public function it_extracts_event_type_correctly_for_each_custodian()
    {
        // Paysera format
        $payseraPayload = json_encode([
            'event' => 'account.updated',
            'event_id' => 'paysera_123',
        ]);
        
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->andReturn(true);
        
        $response = $this->postJson('/api/webhooks/custodian/paysera', json_decode($payseraPayload, true), [
            'X-Paysera-Signature' => 'test',
        ]);
        
        $response->assertAccepted();
        
        $this->assertDatabaseHas('custodian_webhooks', [
            'custodian_name' => 'paysera',
            'event_type' => 'account.updated',
        ]);
        
        // Santander format
        $santanderPayload = json_encode([
            'event_type' => 'payment.completed',
            'id' => 'santander_456',
        ]);
        
        $response = $this->postJson('/api/webhooks/custodian/santander', json_decode($santanderPayload, true), [
            'X-Santander-Signature' => 'test',
        ]);
        
        $response->assertAccepted();
        
        $this->assertDatabaseHas('custodian_webhooks', [
            'custodian_name' => 'santander',
            'event_type' => 'payment.completed',
        ]);
        
        // Mock format
        $mockPayload = json_encode([
            'type' => 'mock.event',
            'id' => 'mock_789',
        ]);
        
        $response = $this->postJson('/api/webhooks/custodian/mock', json_decode($mockPayload, true));
        
        $response->assertAccepted();
        
        $this->assertDatabaseHas('custodian_webhooks', [
            'custodian_name' => 'mock',
            'event_type' => 'mock.event',
        ]);
    }

    /** @test */
    public function it_handles_missing_event_type_gracefully()
    {
        $payload = json_encode([
            'event_id' => 'evt_no_type',
            'data' => ['some' => 'data'],
        ]);
        
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);
        
        $response = $this->postJson('/api/webhooks/custodian/paysera', json_decode($payload, true), [
            'X-Paysera-Signature' => 'test-signature',
        ]);
        
        $response->assertAccepted();
        
        $this->assertDatabaseHas('custodian_webhooks', [
            'custodian_name' => 'paysera',
            'event_type' => 'unknown',
            'event_id' => 'evt_no_type',
        ]);
    }

    /** @test */
    public function it_handles_missing_event_id_gracefully()
    {
        $payload = json_encode([
            'event' => 'account.balance_changed',
            'data' => ['balance' => 1000],
        ]);
        
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);
        
        $response = $this->postJson('/api/webhooks/custodian/paysera', json_decode($payload, true), [
            'X-Paysera-Signature' => 'test-signature',
        ]);
        
        $response->assertAccepted();
        
        $webhook = CustodianWebhook::where('custodian_name', 'paysera')
            ->where('event_type', 'account.balance_changed')
            ->first();
        
        $this->assertNotNull($webhook);
        $this->assertNull($webhook->event_id);
    }

    /** @test */
    public function it_stores_headers_and_payload_correctly()
    {
        $payload = [
            'event' => 'test.event',
            'event_id' => 'evt_headers',
            'nested' => [
                'data' => 'value',
            ],
        ];
        
        $headers = [
            'X-Paysera-Signature' => 'test-signature',
            'X-Request-Id' => 'req_123',
            'Content-Type' => 'application/json',
        ];
        
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);
        
        $response = $this->postJson('/api/webhooks/custodian/paysera', $payload, $headers);
        
        $response->assertAccepted();
        
        $webhook = CustodianWebhook::where('event_id', 'evt_headers')->first();
        
        $this->assertNotNull($webhook);
        $this->assertEquals($payload, $webhook->payload);
        $this->assertArrayHasKey('x-paysera-signature', $webhook->headers);
        $this->assertArrayHasKey('x-request-id', $webhook->headers);
    }

    /** @test */
    public function it_handles_array_header_values()
    {
        $payload = json_encode([
            'event' => 'test.event',
            'event_id' => 'evt_array_headers',
        ]);
        
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->once()
            ->with('paysera', $payload, 'test-signature', Mockery::on(function ($headers) {
                // Verify headers are cleaned (arrays converted to single values)
                return is_string($headers['x-paysera-signature'] ?? null);
            }))
            ->andReturn(true);
        
        // Simulate headers that come as arrays (common in some frameworks)
        $response = $this->call('POST', '/api/webhooks/custodian/paysera', [], [], [], [
            'HTTP_X-Paysera-Signature' => ['test-signature'], // Array value
            'HTTP_Content-Type' => 'application/json',
        ], $payload);
        
        $response->assertAccepted();
    }

    /** @test */
    public function it_returns_500_on_unexpected_error()
    {
        $payload = json_encode([
            'event' => 'test.event',
            'event_id' => 'evt_error',
        ]);
        
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);
        
        // Force an error in the webhook creation by mocking a static method
        CustodianWebhook::saving(function () {
            throw new \RuntimeException('Database error');
        });
        
        $response = $this->postJson('/api/webhooks/custodian/paysera', json_decode($payload, true), [
            'X-Paysera-Signature' => 'test-signature',
        ]);
        
        $response->assertInternalServerError()
            ->assertJsonFragment(['error' => 'Internal server error']);
        
        Queue::assertNotPushed(\App\Jobs\ProcessCustodianWebhook::class);
    }

    /** @test */
    public function it_dispatches_job_with_webhook_uuid()
    {
        $payload = json_encode([
            'event' => 'test.event',
            'event_id' => 'evt_job_test',
        ]);
        
        $this->verificationService
            ->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);
        
        $response = $this->postJson('/api/webhooks/custodian/paysera', json_decode($payload, true), [
            'X-Paysera-Signature' => 'test-signature',
        ]);
        
        $response->assertAccepted();
        
        $webhook = CustodianWebhook::where('event_id', 'evt_job_test')->first();
        
        Queue::assertPushed(\App\Jobs\ProcessCustodianWebhook::class, function ($job) use ($webhook) {
            return $job->webhookId === $webhook->uuid;
        });
    }
}