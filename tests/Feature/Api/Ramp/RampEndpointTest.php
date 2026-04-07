<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Ramp Endpoints', function (): void {
    it('returns supported currencies and limits', function (): void {
        $user = User::factory()->create(['kyc_status' => 'approved']);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/v1/ramp/supported');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'provider',
                'fiat_currencies',
                'crypto_currencies',
                'limits',
            ],
        ]);
    });

    it('requires authentication for quotes', function (): void {
        $response = $this->getJson('/api/v1/ramp/quotes?type=on&fiat_currency=EUR&fiat_amount=100&crypto_currency=USDC');

        $response->assertUnauthorized();
    });

    it('requires authentication to create session', function (): void {
        $response = $this->postJson('/api/v1/ramp/session', [
            'type'            => 'on',
            'fiat_currency'   => 'EUR',
            'fiat_amount'     => 100,
            'crypto_currency' => 'USDC',
            'wallet_address'  => '0x1234567890abcdef1234567890abcdef12345678',
        ]);

        $response->assertUnauthorized();
    });
});
