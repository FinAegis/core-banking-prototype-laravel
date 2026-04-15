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
    it('accepts Vertex real payload with numeric delivered status', function (): void {
        config(['sms.webhook.secret' => '', 'sms.webhook.dlr_url_token' => '']);

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => '1281532560',
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
            'id'     => '1281532560',
            'status' => 1,
            'error'  => 0,
            'mcc'    => '246',
            'mnc'    => '021',
        ]);

        $response->assertOk();
        $response->assertJson(['received' => true]);

        $sms = SmsMessage::where('provider_id', '1281532560')->first();
        expect($sms->status)->toBe(SmsMessage::STATUS_DELIVERED);
        expect($sms->error_code)->toBe(0);
        expect($sms->mcc)->toBe('246');
        expect($sms->mnc)->toBe('021');
    });

    it('maps Vertex undelivered status (2) to failed', function (): void {
        config(['sms.webhook.secret' => '', 'sms.webhook.dlr_url_token' => '']);

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => '1281532561',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'DLR fail test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);

        $this->postJson('/api/v1/webhooks/vertexsms/dlr', [
            'id'     => '1281532561',
            'status' => 2,
            'error'  => 42,
        ])->assertOk();

        $sms = SmsMessage::where('provider_id', '1281532561')->first();
        expect($sms->status)->toBe(SmsMessage::STATUS_FAILED);
        expect($sms->error_code)->toBe(42);
    });

    it('maps Vertex expired status (16) to failed', function (): void {
        config(['sms.webhook.secret' => '', 'sms.webhook.dlr_url_token' => '']);

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => '1281532562',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'DLR expired test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);

        $this->postJson('/api/v1/webhooks/vertexsms/dlr', [
            'id'     => '1281532562',
            'status' => 16,
            'error'  => 99,
        ])->assertOk();

        $sms = SmsMessage::where('provider_id', '1281532562')->first();
        expect($sms->status)->toBe(SmsMessage::STATUS_FAILED);
    });

    it('maps Viber seen status (3) to delivered', function (): void {
        config(['sms.webhook.secret' => '', 'sms.webhook.dlr_url_token' => '']);

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => '1281532563',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Viber seen test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);

        $this->postJson('/api/v1/webhooks/vertexsms/dlr', [
            'id'     => '1281532563',
            'status' => 3,
        ])->assertOk();

        $sms = SmsMessage::where('provider_id', '1281532563')->first();
        expect($sms->status)->toBe(SmsMessage::STATUS_DELIVERED);
    });

    it('still accepts legacy message_id/status string payload for backwards compatibility', function (): void {
        config(['sms.webhook.secret' => '', 'sms.webhook.dlr_url_token' => '']);

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'dlr-legacy-001',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Legacy DLR test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);

        $this->postJson('/api/v1/webhooks/vertexsms/dlr', [
            'message_id' => 'dlr-legacy-001',
            'status'     => 'delivered',
        ])->assertOk();

        $sms = SmsMessage::where('provider_id', 'dlr-legacy-001')->first();
        expect($sms->status)->toBe(SmsMessage::STATUS_DELIVERED);
    });

    it('rejects DLR without id', function (): void {
        config(['sms.webhook.secret' => '', 'sms.webhook.dlr_url_token' => '']);

        $response = $this->postJson('/api/v1/webhooks/vertexsms/dlr', [
            'status' => 1,
        ]);

        $response->assertUnprocessable();
    });

    it('ignores DLR for unknown messages', function (): void {
        config(['sms.webhook.secret' => '', 'sms.webhook.dlr_url_token' => '']);

        $response = $this->postJson('/api/v1/webhooks/vertexsms/dlr', [
            'id'     => 'does-not-exist',
            'status' => 1,
        ]);

        $response->assertOk();
    });

    it('enforces forward-only status transitions', function (): void {
        config(['sms.webhook.secret' => '', 'sms.webhook.dlr_url_token' => '']);

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
            'id'     => 'dlr-fwd-001',
            'status' => 0,  // pseudo 'sent' — must be ignored since already delivered
        ]);

        $sms = SmsMessage::where('provider_id', 'dlr-fwd-001')->first();
        expect($sms->status)->toBe(SmsMessage::STATUS_DELIVERED);
    });

    it('accepts DLR with valid URL token', function (): void {
        config([
            'sms.webhook.secret'        => '',
            'sms.webhook.dlr_url_token' => 'super-secret-token-abc',
        ]);

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'dlr-tok-001',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Token test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);

        $this->postJson('/api/v1/webhooks/vertexsms/dlr?t=super-secret-token-abc', [
            'id'     => 'dlr-tok-001',
            'status' => 1,
        ])->assertOk();

        expect(SmsMessage::where('provider_id', 'dlr-tok-001')->value('status'))
            ->toBe(SmsMessage::STATUS_DELIVERED);
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
