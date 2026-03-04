<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\RampSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RampControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_quote_requires_auth(): void
    {
        $this->getJson('/api/v1/ramp/quote?type=on&fiat=USD&amount=100&crypto=USDC')
            ->assertStatus(401);
    }

    public function test_get_quote_returns_pricing(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $this->getJson('/api/v1/ramp/quote?type=on&fiat=USD&amount=100&crypto=USDC')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['fiat_amount', 'crypto_amount', 'exchange_rate', 'fee', 'fee_currency', 'provider'],
            ])
            ->assertJsonPath('data.fiat_amount', 100)
            ->assertJsonPath('data.provider', 'mock');
    }

    public function test_get_quote_validates_currency(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $this->getJson('/api/v1/ramp/quote?type=on&fiat=XXX&amount=100&crypto=USDC')
            ->assertStatus(422);
    }

    public function test_create_session(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $response = $this->postJson('/api/v1/ramp/session', [
            'type'            => 'on',
            'fiat_currency'   => 'USD',
            'fiat_amount'     => 100,
            'crypto_currency' => 'USDC',
            'wallet_address'  => '0x1234567890abcdef1234567890abcdef12345678',
        ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'provider', 'type', 'fiat_currency', 'fiat_amount', 'crypto_currency', 'status'],
            ]);

        $this->assertEquals('pending', $response->json('data.status'));
        $this->assertEquals('mock', $response->json('data.provider'));

        $this->assertDatabaseHas('ramp_sessions', [
            'user_id'  => $this->user->id,
            'provider' => 'mock',
            'type'     => 'on',
        ]);
    }

    public function test_get_session_status(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $session = RampSession::create([
            'user_id'             => $this->user->id,
            'provider'            => 'mock',
            'type'                => 'on',
            'fiat_currency'       => 'USD',
            'fiat_amount'         => 100,
            'crypto_currency'     => 'USDC',
            'status'              => 'pending',
            'provider_session_id' => 'mock_test_123',
            'metadata'            => [],
        ]);

        $this->getJson("/api/v1/ramp/session/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $session->id);
    }

    public function test_get_session_returns_404_for_other_user(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $other = User::factory()->create();
        $session = RampSession::create([
            'user_id'         => $other->id,
            'provider'        => 'mock',
            'type'            => 'on',
            'fiat_currency'   => 'USD',
            'fiat_amount'     => 100,
            'crypto_currency' => 'USDC',
            'status'          => 'pending',
            'metadata'        => [],
        ]);

        $this->getJson("/api/v1/ramp/session/{$session->id}")
            ->assertStatus(404);
    }

    public function test_list_sessions(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        RampSession::create([
            'user_id'         => $this->user->id,
            'provider'        => 'mock',
            'type'            => 'on',
            'fiat_currency'   => 'USD',
            'fiat_amount'     => 100,
            'crypto_currency' => 'USDC',
            'status'          => 'completed',
            'metadata'        => [],
        ]);

        $this->getJson('/api/v1/ramp/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_webhook_processes_valid_payload(): void
    {
        $session = RampSession::create([
            'user_id'             => $this->user->id,
            'provider'            => 'mock',
            'type'                => 'on',
            'fiat_currency'       => 'USD',
            'fiat_amount'         => 100,
            'crypto_currency'     => 'USDC',
            'status'              => 'pending',
            'provider_session_id' => 'mock_webhook_test',
            'metadata'            => [],
        ]);

        $this->postJson('/api/v1/ramp/webhook/mock', [
            'session_id'    => 'mock_webhook_test',
            'status'        => 'completed',
            'crypto_amount' => 98.50,
        ], ['X-Webhook-Signature' => 'valid'])
            ->assertOk();

        $session->refresh();
        $this->assertEquals('completed', $session->status);
    }

    public function test_create_session_validates_amount_limits(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $this->postJson('/api/v1/ramp/session', [
            'type'            => 'on',
            'fiat_currency'   => 'USD',
            'fiat_amount'     => 0.01,
            'crypto_currency' => 'USDC',
            'wallet_address'  => '0x1234',
        ])
            ->assertStatus(422);
    }
}
