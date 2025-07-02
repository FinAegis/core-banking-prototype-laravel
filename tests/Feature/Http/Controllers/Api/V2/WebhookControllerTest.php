<?php

namespace Tests\Feature\Http\Controllers\Api\V2;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Laravel\Sanctum\Sanctum;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $apiPrefix = '/api/v2';

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_lists_webhooks()
    {
        Sanctum::actingAs($this->user);
        
        Webhook::factory()->count(3)->create();
        
        $response = $this->getJson("{$this->apiPrefix}/webhooks");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'url',
                    'events',
                    'is_active',
                    'description',
                    'created_at',
                    'last_triggered_at',
                ],
            ],
        ]);
        $response->assertJsonCount(3, 'data');
    }

    /** @test */
    public function it_creates_a_webhook()
    {
        Sanctum::actingAs($this->user);
        
        $webhookData = [
            'url' => 'https://example.com/webhook',
            'events' => ['account.created', 'transaction.completed'],
            'description' => 'Test webhook',
            'is_active' => true,
        ];
        
        $response = $this->postJson("{$this->apiPrefix}/webhooks", $webhookData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'url',
                'events',
                'secret',
                'is_active',
                'created_at',
            ],
        ]);
        
        $this->assertDatabaseHas('webhooks', [
            'url' => 'https://example.com/webhook',
            'description' => 'Test webhook',
            'is_active' => true,
        ]);
        
        // Verify secret is returned and starts with whsec_
        $this->assertStringStartsWith('whsec_', $response->json('data.secret'));
    }

    /** @test */
    public function it_validates_webhook_creation()
    {
        Sanctum::actingAs($this->user);
        
        // Missing required fields
        $response = $this->postJson("{$this->apiPrefix}/webhooks", []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url', 'events']);
        
        // Invalid URL (not HTTPS)
        $response = $this->postJson("{$this->apiPrefix}/webhooks", [
            'url' => 'http://example.com/webhook',
            'events' => ['account.created'],
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url']);
        
        // Invalid event
        $response = $this->postJson("{$this->apiPrefix}/webhooks", [
            'url' => 'https://example.com/webhook',
            'events' => ['invalid.event'],
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['events.0']);
        
        // Empty events array
        $response = $this->postJson("{$this->apiPrefix}/webhooks", [
            'url' => 'https://example.com/webhook',
            'events' => [],
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['events']);
    }

    /** @test */
    public function it_shows_webhook_details()
    {
        Sanctum::actingAs($this->user);
        
        $webhook = Webhook::factory()->create();
        
        // Create some deliveries
        WebhookDelivery::factory()->count(5)->create([
            'webhook_uuid' => $webhook->uuid,
            'status' => WebhookDelivery::STATUS_SUCCESS,
        ]);
        WebhookDelivery::factory()->count(2)->create([
            'webhook_uuid' => $webhook->uuid,
            'status' => WebhookDelivery::STATUS_FAILED,
        ]);
        
        $response = $this->getJson("{$this->apiPrefix}/webhooks/{$webhook->uuid}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'url',
                'events',
                'is_active',
                'description',
                'created_at',
                'statistics' => [
                    'total_deliveries',
                    'successful_deliveries',
                    'failed_deliveries',
                    'last_triggered_at',
                ],
            ],
        ]);
        
        $response->assertJson([
            'data' => [
                'id' => $webhook->uuid,
                'statistics' => [
                    'total_deliveries' => 7,
                    'successful_deliveries' => 5,
                    'failed_deliveries' => 2,
                ],
            ],
        ]);
    }

    /** @test */
    public function it_updates_a_webhook()
    {
        Sanctum::actingAs($this->user);
        
        $webhook = Webhook::factory()->create([
            'url' => 'https://old.example.com/webhook',
            'is_active' => true,
        ]);
        
        $updateData = [
            'url' => 'https://new.example.com/webhook',
            'events' => ['transaction.completed', 'transfer.completed'],
            'description' => 'Updated webhook',
            'is_active' => false,
        ];
        
        $response = $this->putJson("{$this->apiPrefix}/webhooks/{$webhook->uuid}", $updateData);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $webhook->uuid,
                'url' => 'https://new.example.com/webhook',
                'is_active' => false,
            ],
        ]);
        
        $webhook->refresh();
        $this->assertEquals('https://new.example.com/webhook', $webhook->url);
        $this->assertEquals(['transaction.completed', 'transfer.completed'], $webhook->events);
        $this->assertEquals('Updated webhook', $webhook->description);
        $this->assertFalse($webhook->is_active);
    }

    /** @test */
    public function it_validates_webhook_updates()
    {
        Sanctum::actingAs($this->user);
        
        $webhook = Webhook::factory()->create();
        
        // Invalid URL
        $response = $this->putJson("{$this->apiPrefix}/webhooks/{$webhook->uuid}", [
            'url' => 'not-a-url',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url']);
        
        // Invalid event
        $response = $this->putJson("{$this->apiPrefix}/webhooks/{$webhook->uuid}", [
            'events' => ['invalid.event'],
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['events.0']);
    }

    /** @test */
    public function it_deletes_a_webhook()
    {
        Sanctum::actingAs($this->user);
        
        $webhook = Webhook::factory()->create();
        
        $response = $this->deleteJson("{$this->apiPrefix}/webhooks/{$webhook->uuid}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('webhooks', ['uuid' => $webhook->uuid]);
    }

    /** @test */
    public function it_lists_webhook_deliveries()
    {
        Sanctum::actingAs($this->user);
        
        $webhook = Webhook::factory()->create();
        
        // Create deliveries with different statuses
        WebhookDelivery::factory()->count(3)->create([
            'webhook_uuid' => $webhook->uuid,
            'status' => WebhookDelivery::STATUS_SUCCESS,
        ]);
        WebhookDelivery::factory()->count(2)->create([
            'webhook_uuid' => $webhook->uuid,
            'status' => WebhookDelivery::STATUS_FAILED,
        ]);
        
        $response = $this->getJson("{$this->apiPrefix}/webhooks/{$webhook->uuid}/deliveries");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'uuid',
                    'webhook_uuid',
                    'event_type',
                    'status',
                    'response_code',
                    'created_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
        
        $response->assertJson([
            'meta' => [
                'total' => 5,
            ],
        ]);
    }

    /** @test */
    public function it_filters_webhook_deliveries_by_status()
    {
        Sanctum::actingAs($this->user);
        
        $webhook = Webhook::factory()->create();
        
        WebhookDelivery::factory()->count(3)->create([
            'webhook_uuid' => $webhook->uuid,
            'status' => WebhookDelivery::STATUS_SUCCESS,
        ]);
        WebhookDelivery::factory()->count(2)->create([
            'webhook_uuid' => $webhook->uuid,
            'status' => WebhookDelivery::STATUS_FAILED,
        ]);
        
        $response = $this->getJson("{$this->apiPrefix}/webhooks/{$webhook->uuid}/deliveries?status=failed");

        $response->assertStatus(200);
        $response->assertJson([
            'meta' => [
                'total' => 2,
            ],
        ]);
        
        foreach ($response->json('data') as $delivery) {
            $this->assertEquals(WebhookDelivery::STATUS_FAILED, $delivery['status']);
        }
    }

    /** @test */
    public function it_lists_available_webhook_events()
    {
        // This endpoint doesn't require authentication
        $response = $this->getJson("{$this->apiPrefix}/webhooks/events");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'account',
                'transaction',
                'transfer',
                'basket',
                'governance',
                'exchange_rate',
            ],
        ]);
        
        // Verify some expected events
        $data = $response->json('data');
        $this->assertContains('account.created', $data['account']);
        $this->assertContains('transaction.completed', $data['transaction']);
        $this->assertContains('transfer.completed', $data['transfer']);
        $this->assertContains('basket.rebalanced', $data['basket']);
        $this->assertContains('poll.created', $data['governance']);
        $this->assertContains('rate.updated', $data['exchange_rate']);
    }

    /** @test */
    public function it_returns_404_for_non_existent_webhook()
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson("{$this->apiPrefix}/webhooks/non-existent-uuid");
        $response->assertStatus(404);
        
        $response = $this->putJson("{$this->apiPrefix}/webhooks/non-existent-uuid", [
            'url' => 'https://example.com/webhook',
        ]);
        $response->assertStatus(404);
        
        $response = $this->deleteJson("{$this->apiPrefix}/webhooks/non-existent-uuid");
        $response->assertStatus(404);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson("{$this->apiPrefix}/webhooks");
        $response->assertStatus(401);
        
        $response = $this->postJson("{$this->apiPrefix}/webhooks", [
            'url' => 'https://example.com/webhook',
            'events' => ['account.created'],
        ]);
        $response->assertStatus(401);
    }
}