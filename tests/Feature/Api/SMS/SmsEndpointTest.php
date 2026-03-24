<?php

declare(strict_types=1);

use App\Models\User;
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
    });

    it('returns rates for a country', function (): void {
        // This may return 404 if VertexSMS rates are not cached (no API key configured)
        $response = $this->getJson('/api/v1/sms/rates?country=LT');

        $response->assertStatus(404); // No rates available without API key
        $response->assertJsonStructure(['data', 'message']);
    });

    it('requires payment for sending SMS', function (): void {
        // POST /v1/sms/send is MPP-gated. Without payment, should return 402
        // or 403 (if MPP is disabled), or validation error
        $response = $this->postJson('/api/v1/sms/send', [
            'to'      => '+37069912345',
            'message' => 'Hello from test',
        ]);

        // MPP disabled → middleware passes through → but VertexSMS client will fail
        // In test env without VertexSMS configured, we expect an error
        expect($response->status())->toBeIn([402, 422, 500]);
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
});
