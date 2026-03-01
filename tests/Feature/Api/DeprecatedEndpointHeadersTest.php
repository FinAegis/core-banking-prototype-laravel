<?php

declare(strict_types=1);

use App\Models\User;

describe('Deprecated Endpoint Headers', function () {
    it('legacy profile endpoint includes Deprecation header', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile');

        $response->assertOk();
        expect($response->headers->get('Deprecation'))->toBe('true');
        expect($response->headers->get('Sunset'))->toBe('2026-09-01');
        expect($response->headers->get('Link'))->toContain('successor-version');
    });

    it('legacy KYC documents endpoint includes Deprecation header', function () {
        $user = User::factory()->create();

        // Just verify the middleware is applied (will get validation error, but headers should be present)
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/kyc/documents', []);

        // Even on error, deprecation headers should be present
        expect($response->headers->get('Deprecation'))->toBe('true');
        expect($response->headers->get('Sunset'))->toBe('2026-09-01');
    });

    it('non-deprecated endpoints do not include Deprecation header', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/accounts');

        expect($response->headers->has('Deprecation'))->toBeFalse();
    });
});
