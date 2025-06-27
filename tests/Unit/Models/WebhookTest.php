<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebhookTest extends TestCase
{

    /** @test */
    public function it_can_create_a_webhook()
    {
        $webhook = Webhook::create([
            'uuid' => 'test-uuid',
            'name' => 'Test Webhook',
            'url' => 'https://example.com/webhook',
            'events' => ['account.created', 'transfer.completed'],
            'is_active' => true,
            'secret' => 'secret123',
            'retry_attempts' => 3,
        ]);

        $this->assertEquals('test-uuid', $webhook->uuid);
        $this->assertEquals('https://example.com/webhook', $webhook->url);
        $this->assertContains('account.created', $webhook->events);
        $this->assertTrue($webhook->is_active);
    }

    /** @test */
    public function it_has_deliveries_relationship()
    {
        $webhook = Webhook::factory()->create();
        
        WebhookDelivery::factory()->create([
            'webhook_uuid' => $webhook->uuid,
            'event_type' => 'test.event',
        ]);

        $this->assertCount(1, $webhook->deliveries);
        $this->assertInstanceOf(WebhookDelivery::class, $webhook->deliveries->first());
    }

    /** @test */
    public function it_has_active_scope()
    {
        Webhook::factory()->create(['is_active' => true]);
        Webhook::factory()->create(['is_active' => false]);

        $activeWebhooks = Webhook::active()->get();

        $this->assertCount(1, $activeWebhooks);
        $this->assertTrue($activeWebhooks->first()->is_active);
    }

    /** @test */
    public function it_has_for_event_scope()
    {
        Webhook::factory()->create([
            'events' => ['account.created', 'account.updated'],
        ]);

        Webhook::factory()->create([
            'events' => ['transfer.created'],
        ]);

        $accountWebhooks = Webhook::forEvent('account.created')->get();

        $this->assertCount(1, $accountWebhooks);
        $this->assertContains('account.created', $accountWebhooks->first()->events);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $webhook = Webhook::create([
            'uuid' => 'test-uuid',
            'name' => 'Test Webhook',
            'url' => 'https://example.com/webhook',
            'events' => ['account.created'],
            'is_active' => true,
        ]);

        $fresh = Webhook::find($webhook->uuid);

        $this->assertIsArray($fresh->events);
        $this->assertIsBool($fresh->is_active);
    }
}