<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_webhook()
    {
        $webhook = Webhook::create([
            'uuid' => 'test-uuid',
            'url' => 'https://example.com/webhook',
            'event_types' => ['account.created', 'transfer.completed'],
            'is_active' => true,
            'signing_secret' => 'secret123',
            'retry_count' => 0,
            'metadata' => ['owner' => 'test'],
        ]);

        $this->assertEquals('test-uuid', $webhook->uuid);
        $this->assertEquals('https://example.com/webhook', $webhook->url);
        $this->assertContains('account.created', $webhook->event_types);
        $this->assertTrue($webhook->is_active);
    }

    /** @test */
    public function it_has_deliveries_relationship()
    {
        $webhook = Webhook::factory()->create();
        
        WebhookDelivery::factory()->create([
            'webhook_id' => $webhook->id,
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
            'event_types' => ['account.created', 'account.updated'],
        ]);

        Webhook::factory()->create([
            'event_types' => ['transfer.created'],
        ]);

        $accountWebhooks = Webhook::forEvent('account.created')->get();

        $this->assertCount(1, $accountWebhooks);
        $this->assertContains('account.created', $accountWebhooks->first()->event_types);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $webhook = Webhook::create([
            'uuid' => 'test-uuid',
            'url' => 'https://example.com/webhook',
            'event_types' => ['account.created'],
            'is_active' => true,
            'metadata' => ['key' => 'value'],
        ]);

        $fresh = Webhook::find($webhook->id);

        $this->assertIsArray($fresh->event_types);
        $this->assertIsBool($fresh->is_active);
        $this->assertIsArray($fresh->metadata);
        $this->assertEquals('value', $fresh->metadata['key']);
    }
}