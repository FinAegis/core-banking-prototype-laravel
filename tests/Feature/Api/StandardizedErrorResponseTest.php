<?php

declare(strict_types=1);

use App\Models\User;

describe('Standardized API Error Responses', function () {
    it('includes error code on validation errors', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/accounts', []);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors', 'error', 'request_id']);
        $response->assertJson(['error' => 'VALIDATION_ERROR']);
    });

    it('includes error code on 404 responses', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/accounts/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
        $json = $response->json();
        expect($json)->toHaveKey('error');
        expect($json)->toHaveKey('request_id');
    });

    it('includes error code on unauthenticated requests', function () {
        $response = $this->getJson('/api/accounts');

        $response->assertStatus(401);
        $json = $response->json();
        expect($json)->toHaveKey('error');
        expect($json['error'])->toBe('UNAUTHENTICATED');
    });

    it('preserves existing error codes from controllers', function () {
        $user = User::factory()->create();
        Laravel\Sanctum\Sanctum::actingAs($user, ['delete']);

        $account = App\Domain\Account\Models\Account::factory()->forUser($user)->create([
            'frozen' => true,
        ]);

        $response = $this->deleteJson("/api/accounts/{$account->uuid}");

        // Controller returns its own error code which should not be overwritten
        $json = $response->json();
        expect($json)->toHaveKey('error');
        expect($json['error'])->toBe('ACCOUNT_FROZEN');
    });

    it('includes request_id from X-Request-ID header', function () {
        $response = $this->getJson('/api/accounts', [
            'X-Request-ID' => 'test-req-12345',
        ]);

        $response->assertStatus(401);
        $json = $response->json();
        expect($json['request_id'])->toBe('test-req-12345');
    });
});
