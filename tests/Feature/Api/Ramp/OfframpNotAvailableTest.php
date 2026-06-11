<?php

/**
 * Offramp honesty: v1 ships bank-rail ONRAMP only (Bridge offramp lands in
 * v1.1). The API surface must say so cleanly — HTTP 422 with the explicit
 * OFFRAMP_NOT_AVAILABLE error code and a message naming v1.1 — never a 500
 * and never the generic SESSION_ERROR that mobile renders as a failure toast.
 */

declare(strict_types=1);

use App\Domain\Ramp\Exceptions\OfframpNotAvailableException;
use App\Domain\Ramp\Providers\BridgeProvider;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Offramp not available (Bridge v1)', function (): void {
    it('returns 422 OFFRAMP_NOT_AVAILABLE when type=off is requested on the Bridge provider', function (): void {
        config(['ramp.default_provider' => 'bridge']);

        $user = User::factory()->create(['kyc_status' => 'approved']);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/v1/ramp/session', [
            'type'            => 'off',
            'fiat_currency'   => 'EUR',
            'fiat_amount'     => 100,
            'crypto_currency' => 'USDC',
            'wallet_address'  => '0x1234567890abcdef1234567890abcdef12345678',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
        $response->assertJsonPath('error.code', 'OFFRAMP_NOT_AVAILABLE');

        $message = (string) $response->json('error.message');
        expect($message)->toContain('v1.1');
        expect($message)->toContain('off');
    });

    it('keeps the onramp path on the Bridge provider unaffected by the offramp mapping', function (): void {
        config(['ramp.default_provider' => 'bridge']);

        $user = User::factory()->create(['kyc_status' => 'approved']);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        // No bridge_customers row exists, so onramp fails too — but through
        // the generic SESSION_ERROR (a real precondition failure), proving
        // OFFRAMP_NOT_AVAILABLE is reserved for the type=off contract gap.
        $response = $this->postJson('/api/v1/ramp/session', [
            'type'            => 'on',
            'fiat_currency'   => 'EUR',
            'fiat_amount'     => 100,
            'crypto_currency' => 'USDC',
            'wallet_address'  => '0x1234567890abcdef1234567890abcdef12345678',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'SESSION_ERROR');
    });

    it('throws OfframpNotAvailableException (a RuntimeException) from BridgeProvider for type=off', function (): void {
        $provider = app()->makeWith(BridgeProvider::class, []);

        expect(fn () => $provider->createSession([
            'type'            => 'off',
            'fiat_currency'   => 'USD',
            'fiat_amount'     => '100.00',
            'crypto_currency' => 'USDC',
            'wallet_address'  => '0x0000000000000000000000000000000000000001',
            'quote_id'        => null,
        ]))->toThrow(OfframpNotAvailableException::class, 'deferred to v1.1');
    });
});
