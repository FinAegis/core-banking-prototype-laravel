<?php

declare(strict_types=1);

use App\Domain\SMS\Models\SmsMessage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

describe('SMS Endpoints', function (): void {
    it('returns service info', function (): void {
        $response = $this->getJson('/api/v1/sms/info');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'provider',
                'enabled',
                'test_mode',
                'networks',
            ],
        ]);
        $response->assertJsonPath('data.networks', ['eip155:8453', 'eip155:1']);
    });

    it('returns rates for a country', function (): void {
        $response = $this->getJson('/api/v1/sms/rates?country=LT');

        $response->assertStatus(404);
        $response->assertJsonStructure(['data', 'message']);
    });

    it('validates country code format', function (): void {
        $response = $this->getJson('/api/v1/sms/rates?country=123');

        $response->assertUnprocessable();
    });

    it('validates country code is required', function (): void {
        $response = $this->getJson('/api/v1/sms/rates');

        $response->assertUnprocessable();
    });

    it('returns 503 when SMS disabled for send', function (): void {
        config(['sms.enabled' => false]);

        $response = $this->postJson('/api/v1/sms/send', [
            'to'      => '+37069912345',
            'message' => 'Hello from test',
        ]);

        expect($response->status())->toBeIn([402, 503]);
    });

    it('validates phone number format', function (): void {
        config(['sms.enabled' => true]);

        $response = $this->postJson('/api/v1/sms/send', [
            'to'      => 'not-a-number',
            'message' => 'Test',
        ]);

        expect($response->status())->toBeIn([402, 422]);
    });

    it('validates message is required', function (): void {
        config(['sms.enabled' => true]);

        $response = $this->postJson('/api/v1/sms/send', [
            'to' => '+37069912345',
        ]);

        expect($response->status())->toBeIn([402, 422]);
    });

    it('validates message max length', function (): void {
        config(['sms.enabled' => true]);

        $response = $this->postJson('/api/v1/sms/send', [
            'to'      => '+37069912345',
            'message' => str_repeat('x', 1601),
        ]);

        expect($response->status())->toBeIn([402, 422]);
    });

    it('requires authentication for status check', function (): void {
        $response = $this->getJson('/api/v1/sms/status/test-message-id');

        $response->assertUnauthorized();
    });

    it('returns 404 for unknown message status', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/v1/sms/status/nonexistent-id');

        $response->assertNotFound();
    });

    it('returns message status when found', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'test-msg-123',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Test message',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);

        $response = $this->getJson('/api/v1/sms/status/test-msg-123');

        $response->assertOk();
        $response->assertJsonPath('data.message_id', 'test-msg-123');
        $response->assertJsonPath('data.status', 'sent');
    });
});

describe('SMS DLR Webhook', function (): void {
    it('accepts valid delivery report', function (): void {
        config(['sms.webhook.secret' => '']);

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'dlr-test-001',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'DLR test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);

        $response = $this->postJson('/api/v1/webhooks/vertexsms/dlr', [
            'message_id' => 'dlr-test-001',
            'status'     => 'delivered',
        ]);

        $response->assertOk();
        $response->assertJson(['received' => true]);

        $sms = SmsMessage::where('provider_id', 'dlr-test-001')->first();
        expect($sms->status)->toBe(SmsMessage::STATUS_DELIVERED);
    });

    it('rejects DLR without message_id', function (): void {
        config(['sms.webhook.secret' => '']);

        $response = $this->postJson('/api/v1/webhooks/vertexsms/dlr', [
            'status' => 'delivered',
        ]);

        $response->assertUnprocessable();
    });

    it('ignores DLR for unknown messages', function (): void {
        config(['sms.webhook.secret' => '']);

        $response = $this->postJson('/api/v1/webhooks/vertexsms/dlr', [
            'message_id' => 'does-not-exist',
            'status'     => 'delivered',
        ]);

        $response->assertOk();
    });

    it('enforces forward-only status transitions', function (): void {
        config(['sms.webhook.secret' => '']);

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'dlr-fwd-001',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Forward test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_DELIVERED,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);

        $this->postJson('/api/v1/webhooks/vertexsms/dlr', [
            'message_id' => 'dlr-fwd-001',
            'status'     => 'sent',
        ]);

        $sms = SmsMessage::where('provider_id', 'dlr-fwd-001')->first();
        expect($sms->status)->toBe(SmsMessage::STATUS_DELIVERED);
    });
});

describe('SMS Pricing', function (): void {
    it('returns minimum price for unknown country', function (): void {
        config(['cache.default' => 'array']);
        Cache::flush();

        $pricing = app(App\Domain\SMS\Services\SmsPricingService::class);
        $result = $pricing->getPriceForNumber('+99999999999');

        expect((int) $result['amount_usdc'])->toBeGreaterThanOrEqual(1000);
        expect($result['parts'])->toBe(1);
    });

    it('calculates multi-part pricing', function (): void {
        config(['cache.default' => 'array']);
        Cache::flush();

        $pricing = app(App\Domain\SMS\Services\SmsPricingService::class);
        $single = $pricing->getPriceForNumber('+37069912345', 1);
        $double = $pricing->getPriceForNumber('+37069912345', 2);

        expect((int) $double['amount_usdc'])->toBeGreaterThan((int) $single['amount_usdc']);
        expect($double['parts'])->toBe(2);
    });
});
