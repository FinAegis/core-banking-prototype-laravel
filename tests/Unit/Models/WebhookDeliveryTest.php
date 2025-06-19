<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected Webhook $webhook;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->webhook = Webhook::factory()->create();
    }

    /** @test */
    public function it_can_create_a_webhook_delivery()
    {
        $delivery = WebhookDelivery::create([
            'uuid' => 'delivery-uuid',
            'webhook_id' => $this->webhook->id,
            'event_type' => 'test.event',
            'event_id' => 'evt_123',
            'payload' => ['test' => 'data'],
            'status' => 'pending',
            'attempts' => 0,
        ]);

        $this->assertEquals('delivery-uuid', $delivery->uuid);
        $this->assertEquals('test.event', $delivery->event_type);
        $this->assertEquals('pending', $delivery->status);
        $this->assertEquals(0, $delivery->attempts);
    }

    /** @test */
    public function it_belongs_to_webhook()
    {
        $delivery = WebhookDelivery::factory()->create([
            'webhook_id' => $this->webhook->id,
        ]);

        $this->assertInstanceOf(Webhook::class, $delivery->webhook);
        $this->assertEquals($this->webhook->id, $delivery->webhook->id);
    }

    /** @test */
    public function it_can_mark_as_delivered()
    {
        $delivery = WebhookDelivery::factory()->create([
            'webhook_id' => $this->webhook->id,
            'status' => 'pending',
        ]);

        $delivery->markAsDelivered(200, ['success' => true]);

        $this->assertEquals('delivered', $delivery->status);
        $this->assertEquals(200, $delivery->response_status);
        $this->assertEquals(['success' => true], $delivery->response_body);
        $this->assertNotNull($delivery->delivered_at);
    }

    /** @test */
    public function it_can_mark_as_failed()
    {
        $delivery = WebhookDelivery::factory()->create([
            'webhook_id' => $this->webhook->id,
            'status' => 'pending',
            'attempts' => 1,
        ]);

        $delivery->markAsFailed('Connection timeout', 0);

        $this->assertEquals('failed', $delivery->status);
        $this->assertEquals('Connection timeout', $delivery->last_error);
        $this->assertEquals(0, $delivery->response_status);
        $this->assertEquals(2, $delivery->attempts);
        $this->assertNotNull($delivery->last_attempt_at);
    }

    /** @test */
    public function it_has_pending_scope()
    {
        WebhookDelivery::factory()->create([
            'webhook_id' => $this->webhook->id,
            'status' => 'pending',
        ]);

        WebhookDelivery::factory()->create([
            'webhook_id' => $this->webhook->id,
            'status' => 'delivered',
        ]);

        $pending = WebhookDelivery::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    /** @test */
    public function it_has_failed_scope()
    {
        WebhookDelivery::factory()->create([
            'webhook_id' => $this->webhook->id,
            'status' => 'failed',
        ]);

        WebhookDelivery::factory()->create([
            'webhook_id' => $this->webhook->id,
            'status' => 'delivered',
        ]);

        $failed = WebhookDelivery::failed()->get();

        $this->assertCount(1, $failed);
        $this->assertEquals('failed', $failed->first()->status);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $delivery = WebhookDelivery::create([
            'uuid' => 'test-uuid',
            'webhook_id' => $this->webhook->id,
            'event_type' => 'test.event',
            'payload' => ['test' => 'data'],
            'status' => 'pending',
            'delivered_at' => '2025-06-18 12:00:00',
        ]);

        $fresh = WebhookDelivery::find($delivery->id);

        $this->assertIsArray($fresh->payload);
        $this->assertIsArray($fresh->response_body);
        $this->assertInstanceOf(\Carbon\Carbon::class, $fresh->delivered_at);
    }
}