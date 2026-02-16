<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Privacy;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PrivacyShieldedEndpointsTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write'])->plainTextToken;
    }

    public function test_get_shielded_balances_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/privacy/balances');

        $response->assertUnauthorized();
    }

    public function test_get_shielded_balances_returns_per_token_balances(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/balances');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['token', 'balance', 'network'],
                ],
            ]);
    }

    public function test_get_total_shielded_balance_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/privacy/total-balance');

        $response->assertUnauthorized();
    }

    public function test_get_total_shielded_balance_returns_aggregate(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/total-balance');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['total_balance', 'currency'],
            ]);
    }

    public function test_get_privacy_transactions_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/privacy/transactions');

        $response->assertUnauthorized();
    }

    public function test_get_privacy_transactions_returns_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/transactions');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_shield_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/privacy/shield', [
            'amount'  => '100.00',
            'token'   => 'USDC',
            'network' => 'polygon',
        ]);

        $response->assertUnauthorized();
    }

    public function test_shield_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/shield', []);

        $response->assertUnprocessable();
    }

    public function test_unshield_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/privacy/unshield', [
            'amount'    => '50.00',
            'token'     => 'USDC',
            'network'   => 'polygon',
            'recipient' => '0x1234567890123456789012345678901234567890',
        ]);

        $response->assertUnauthorized();
    }

    public function test_private_transfer_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/privacy/transfer', [
            'amount'  => '25.00',
            'token'   => 'USDC',
            'network' => 'polygon',
        ]);

        $response->assertUnauthorized();
    }

    public function test_viewing_key_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/privacy/viewing-key');

        $response->assertUnauthorized();
    }

    public function test_viewing_key_returns_deterministic_key(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/viewing-key');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['viewing_key', 'created_at'],
            ]);

        // Key should start with 0x
        $this->assertStringStartsWith('0x', $response->json('data.viewing_key'));

        // Same user should get the same key
        $response2 = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/viewing-key');

        $this->assertEquals(
            $response->json('data.viewing_key'),
            $response2->json('data.viewing_key')
        );
    }

    public function test_proof_of_innocence_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/privacy/proof-of-innocence');

        $response->assertUnauthorized();
    }

    public function test_verify_proof_of_innocence_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/privacy/proof-of-innocence/test-proof-id/verify');

        $response->assertUnauthorized();
    }

    public function test_verify_proof_of_innocence_returns_result(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/proof-of-innocence/test-proof-id/verify');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['proof_id', 'valid', 'verified_at'],
            ]);
    }
}
