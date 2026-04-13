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

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['kyc_status' => 'approved']);
    }

    public function test_quotes_requires_auth(): void
    {
        $this->getJson('/api/v1/ramp/quotes?type=on&fiat=USD&amount=100&crypto=USDC')
            ->assertStatus(401);
    }

    public function test_get_quotes_returns_multiple_providers(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $this->getJson('/api/v1/ramp/quotes?type=on&fiat=USD&amount=100&crypto=USDC')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'quotes' => [
                        '*' => ['provider_name', 'quote_id', 'fiat_amount', 'crypto_amount', 'exchange_rate', 'fee', 'network_fee', 'fee_currency', 'payment_methods'],
                    ],
                    'provider',
                    'valid_until',
                ],
            ])
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonCount(2, 'data.quotes');
    }

    public function test_get_quotes_validates_currency(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $this->getJson('/api/v1/ramp/quotes?type=on&fiat=XXX&amount=100&crypto=USDC')
            ->assertStatus(422);
    }

    public function test_create_session(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $wallet = '0x1234567890abcdef1234567890abcdef12345678';

        $response = $this->postJson('/api/v1/ramp/session', [
            'type'            => 'on',
            'fiat_currency'   => 'USD',
            'fiat_amount'     => 100,
            'crypto_currency' => 'USDC',
            'wallet_address'  => $wallet,
        ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'provider', 'type', 'fiat_currency', 'fiat_amount', 'crypto_currency', 'status', 'checkout_url', 'wallet_address'],
            ]);

        $this->assertEquals('pending', $response->json('data.status'));
        $this->assertEquals('mock', $response->json('data.provider'));
        $this->assertEquals($wallet, $response->json('data.wallet_address'));

        $this->assertDatabaseHas('ramp_sessions', [
            'user_id'        => $this->user->id,
            'provider'       => 'mock',
            'type'           => 'on',
            'wallet_address' => $wallet,
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
        ], ['X-Mock-Signature' => 'valid'])
            ->assertOk();

        $session->refresh();
        $this->assertEquals('completed', $session->status);
    }

    public function test_webhook_skips_terminal_session(): void
    {
        $session = RampSession::create([
            'user_id'             => $this->user->id,
            'provider'            => 'mock',
            'type'                => 'on',
            'fiat_currency'       => 'USD',
            'fiat_amount'         => 100,
            'crypto_currency'     => 'USDC',
            'status'              => 'completed',
            'provider_session_id' => 'mock_terminal_test',
            'metadata'            => [],
        ]);

        $this->postJson('/api/v1/ramp/webhook/mock', [
            'session_id'    => 'mock_terminal_test',
            'status'        => 'failed',
            'crypto_amount' => 0,
        ], ['X-Mock-Signature' => 'valid'])
            ->assertOk();

        $session->refresh();
        $this->assertEquals('completed', $session->status);
    }

    public function test_webhook_rejects_provider_mismatch(): void
    {
        $session = RampSession::create([
            'user_id'             => $this->user->id,
            'provider'            => 'onramper',
            'type'                => 'on',
            'fiat_currency'       => 'USD',
            'fiat_amount'         => 100,
            'crypto_currency'     => 'USDC',
            'status'              => 'pending',
            'provider_session_id' => 'mismatch_test',
            'metadata'            => [],
        ]);

        $this->postJson('/api/v1/ramp/webhook/mock', [
            'session_id' => 'mismatch_test',
            'status'     => 'completed',
        ], ['X-Mock-Signature' => 'valid'])
            ->assertOk();

        $session->refresh();
        $this->assertEquals('pending', $session->status);
    }

    public function test_webhook_normalizes_status(): void
    {
        $session = RampSession::create([
            'user_id'             => $this->user->id,
            'provider'            => 'mock',
            'type'                => 'on',
            'fiat_currency'       => 'USD',
            'fiat_amount'         => 100,
            'crypto_currency'     => 'USDC',
            'status'              => 'pending',
            'provider_session_id' => 'normalize_test',
            'metadata'            => [],
        ]);

        $this->postJson('/api/v1/ramp/webhook/mock', [
            'session_id' => 'normalize_test',
            'status'     => 'success',
        ], ['X-Mock-Signature' => 'valid'])
            ->assertOk();

        $session->refresh();
        $this->assertEquals('completed', $session->status);
    }

    public function test_supported_returns_modes_with_types(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $this->getJson('/api/v1/ramp/supported')
            ->assertOk()
            ->assertJsonPath('data.modes.0.type', 'on')
            ->assertJsonPath('data.modes.0.label', 'Buy Crypto')
            ->assertJsonPath('data.modes.1.type', 'off')
            ->assertJsonPath('data.modes.1.label', 'Sell Crypto');
    }

    public function test_create_session_validates_amount_limits(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $this->postJson('/api/v1/ramp/session', [
            'type'            => 'on',
            'fiat_currency'   => 'USD',
            'fiat_amount'     => 0.01,
            'crypto_currency' => 'USDC',
            'wallet_address'  => '0x1234',
        ])
            ->assertStatus(422);
    }

    public function test_supported_returns_provider_info(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $this->getJson('/api/v1/ramp/supported')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['provider', 'fiat_currencies', 'crypto_currencies', 'modes', 'limits'],
            ]);
    }
}
