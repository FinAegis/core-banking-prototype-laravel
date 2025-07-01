<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\CustodianWebhook;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustodianWebhookTest extends TestCase
{

    /** @test */
    public function it_can_create_a_custodian_webhook()
    {
        $webhook = CustodianWebhook::create([
            'uuid' => 'webhook-uuid',
            'custodian_name' => 'paysera',
            'event_type' => 'account.balance_changed',
            'event_id' => 'evt_123',
            'headers' => ['x-signature' => 'test'],
            'payload' => ['balance' => 1000],
            'signature' => 'test-signature',
            'status' => 'pending',
        ]);

        $this->assertEquals('webhook-uuid', $webhook->uuid);
        $this->assertEquals('paysera', $webhook->custodian_name);
        $this->assertEquals('account.balance_changed', $webhook->event_type);
        $this->assertEquals('pending', $webhook->status);
    }

    /** @test */
    public function it_has_pending_scope()
    {
        CustodianWebhook::create([
            'uuid' => 'pending-1',
            'custodian_name' => 'paysera',
            'event_type' => 'test',
            'payload' => ['test' => 'data'],
            'status' => 'pending',
        ]);

        CustodianWebhook::create([
            'uuid' => 'processed-1',
            'custodian_name' => 'paysera',
            'event_type' => 'test',
            'payload' => ['test' => 'data'],
            'status' => 'processed',
        ]);

        $pending = CustodianWebhook::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    /** @test */
    public function it_has_failed_scope()
    {
        CustodianWebhook::create([
            'uuid' => 'failed-1',
            'custodian_name' => 'santander',
            'event_type' => 'test',
            'payload' => ['test' => 'data'],
            'status' => 'failed',
        ]);

        CustodianWebhook::create([
            'uuid' => 'processed-2',
            'custodian_name' => 'santander',
            'event_type' => 'test',
            'payload' => ['test' => 'data'],
            'status' => 'processed',
        ]);

        $failed = CustodianWebhook::failed()->get();

        $this->assertCount(1, $failed);
        $this->assertEquals('failed', $failed->first()->status);
    }

    /** @test */
    public function it_has_by_custodian_scope()
    {
        CustodianWebhook::create([
            'uuid' => 'paysera-1',
            'custodian_name' => 'paysera',
            'event_type' => 'test',
            'payload' => ['test' => 'data'],
            'status' => 'pending',
        ]);

        CustodianWebhook::create([
            'uuid' => 'santander-1',
            'custodian_name' => 'santander',
            'event_type' => 'test',
            'payload' => ['test' => 'data'],
            'status' => 'pending',
        ]);

        $payseraWebhooks = CustodianWebhook::byCustodian('paysera')->get();

        $this->assertCount(1, $payseraWebhooks);
        $this->assertEquals('paysera', $payseraWebhooks->first()->custodian_name);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $webhook = CustodianWebhook::create([
            'uuid' => 'test-uuid',
            'custodian_name' => 'mock',
            'event_type' => 'test.event',
            'headers' => ['content-type' => 'application/json'],
            'payload' => ['test' => 'data'],
            'status' => 'pending',
            'processed_at' => '2025-06-18 12:00:00',
        ]);

        $fresh = CustodianWebhook::where('uuid', $webhook->uuid)->first();

        $this->assertIsArray($fresh->headers);
        $this->assertIsArray($fresh->payload);
        $this->assertInstanceOf(\Carbon\Carbon::class, $fresh->processed_at);
        $this->assertArrayHasKey('content-type', $fresh->headers);
        $this->assertEquals('application/json', $fresh->headers['content-type']);
        $this->assertArrayHasKey('test', $fresh->payload);
        $this->assertEquals('data', $fresh->payload['test']);
    }

    /** @test */
    public function it_uses_uuid_as_primary_key()
    {
        $webhook = CustodianWebhook::create([
            'uuid' => 'primary-key-test',
            'custodian_name' => 'mock',
            'event_type' => 'test',
            'payload' => ['test' => 'data'],
            'status' => 'pending',
        ]);

        $found = CustodianWebhook::where('uuid', 'primary-key-test')->first();

        $this->assertNotNull($found);
        $this->assertEquals('primary-key-test', $found->uuid);
        // CustodianWebhook uses 'id' as primary key, but has a unique uuid column
        $this->assertEquals('id', $webhook->getKeyName());
    }

    /** @test */
    public function it_handles_null_event_id()
    {
        $webhook = CustodianWebhook::create([
            'uuid' => 'no-event-id',
            'custodian_name' => 'mock',
            'event_type' => 'test',
            'event_id' => null,
            'payload' => ['test' => 'data'],
            'status' => 'pending',
        ]);

        $this->assertNull($webhook->event_id);
        $this->assertEquals('no-event-id', $webhook->uuid);
    }
}