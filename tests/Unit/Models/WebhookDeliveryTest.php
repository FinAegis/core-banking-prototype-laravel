<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class WebhookDeliveryTest extends TestCase
{

    protected Webhook $webhook;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->webhook = Webhook::factory()->create();
    }

    #[Test]
    public function it_can_create_a_webhook_delivery()
    {
        $delivery = WebhookDelivery::create([
            'uuid' => 'delivery-uuid',
            'webhook_uuid' => $this->webhook->uuid,
            'event_type' => 'test.event',
            'payload' => ['test' => 'data'],
            'status' => 'pending',
            'attempt_number' => 1,
        ]);

        $this->assertEquals('delivery-uuid', $delivery->uuid);
        $this->assertEquals('test.event', $delivery->event_type);
        $this->assertEquals('pending', $delivery->status);
        $this->assertEquals(1, $delivery->attempt_number);
    }

    #[Test]
    public function it_belongs_to_webhook()
    {
        $delivery = WebhookDelivery::factory()->create([
            'webhook_uuid' => $this->webhook->uuid,
        ]);

        $this->assertInstanceOf(Webhook::class, $delivery->webhook);
        $this->assertEquals($this->webhook->id, $delivery->webhook->id);
    }

    #[Test]
    public function it_can_mark_as_delivered()
    {
        $delivery = WebhookDelivery::factory()->create([
            'webhook_uuid' => $this->webhook->uuid,
            'status' => 'pending',
        ]);

        $delivery->markAsDelivered(200, '{"success": true}', ['Content-Type' => 'application/json'], 150);

        $this->assertEquals('delivered', $delivery->status);
        $this->assertEquals(200, $delivery->response_status);
        $this->assertEquals('{"success": true}', $delivery->response_body);
        $this->assertNotNull($delivery->delivered_at);
    }

    #[Test]
    public function it_can_mark_as_failed()
    {
        $delivery = WebhookDelivery::factory()->create([
            'webhook_uuid' => $this->webhook->uuid,
            'status' => 'pending',
            'attempt_number' => 1,
        ]);

        $delivery->markAsFailed('Connection timeout', 0, null);

        $this->assertEquals('failed', $delivery->status);
        $this->assertEquals('Connection timeout', $delivery->error_message);
        $this->assertEquals(0, $delivery->response_status);
        $this->assertNotNull($delivery->next_retry_at);
    }

    #[Test]
    public function it_has_pending_scope()
    {
        WebhookDelivery::factory()->create([
            'webhook_uuid' => $this->webhook->uuid,
            'status' => 'pending',
        ]);

        WebhookDelivery::factory()->create([
            'webhook_uuid' => $this->webhook->uuid,
            'status' => 'delivered',
        ]);

        $pending = WebhookDelivery::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    #[Test]
    public function it_has_failed_scope()
    {
        WebhookDelivery::factory()->create([
            'webhook_uuid' => $this->webhook->uuid,
            'status' => 'failed',
        ]);

        WebhookDelivery::factory()->create([
            'webhook_uuid' => $this->webhook->uuid,
            'status' => 'delivered',
        ]);

        $failed = WebhookDelivery::failed()->get();

        $this->assertCount(1, $failed);
        $this->assertEquals('failed', $failed->first()->status);
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $delivery = WebhookDelivery::create([
            'uuid' => 'test-uuid',
            'webhook_uuid' => $this->webhook->uuid,
            'event_type' => 'test.event',
            'payload' => ['test' => 'data'],
            'status' => 'pending',
            'delivered_at' => '2025-06-18 12:00:00',
        ]);

        $fresh = WebhookDelivery::find($delivery->uuid);

        $this->assertIsArray($fresh->payload);
        $this->assertNull($fresh->response_body);
        $this->assertInstanceOf(\Carbon\Carbon::class, $fresh->delivered_at);
    }
}