<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class RiskAnalysisControllerTest extends ControllerTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    #[Test]
    public function test_get_user_risk_profile_returns_risk_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $userId = 'user-123';
        $response = $this->getJson("/api/risk/users/{$userId}/profile");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user_id',
                    'risk_score',
                    'risk_level',
                ],
            ])
            ->assertJson([
                'data' => [
                    'user_id'    => $userId,
                    'risk_score' => 0,
                    'risk_level' => 'low',
                ],
            ]);
    }

    #[Test]
    public function test_get_user_risk_profile_requires_authentication(): void
    {
        $response = $this->getJson('/api/risk/users/user-123/profile');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_analyze_transaction_returns_risk_analysis(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $transactionId = 'txn-456';
        $response = $this->getJson("/api/risk/transactions/{$transactionId}/analyze");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'transaction_id',
                    'risk_score',
                    'risk_factors',
                ],
            ])
            ->assertJson([
                'data' => [
                    'transaction_id' => $transactionId,
                    'risk_score'     => 0,
                    'risk_factors'   => [],
                ],
            ]);
    }

    #[Test]
    public function test_analyze_transaction_requires_authentication(): void
    {
        $response = $this->getJson('/api/risk/transactions/txn-456/analyze');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_calculate_risk_score_with_basic_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/risk/calculate', [
            'amount'           => 1000.00,
            'currency'         => 'EUR',
            'user_id'          => $this->user->id,
            'transaction_type' => 'transfer',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'risk_score',
                    'risk_level',
                ],
            ])
            ->assertJson([
                'data' => [
                    'risk_score' => 0,
                    'risk_level' => 'low',
                ],
            ]);
    }

    #[Test]
    public function test_calculate_risk_score_with_full_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/risk/calculate', [
            'amount'              => 50000.00,
            'currency'            => 'EUR',
            'user_id'             => $this->user->id,
            'transaction_type'    => 'withdrawal',
            'destination_country' => 'US',
            'device_fingerprint'  => 'fingerprint-123',
            'ip_address'          => '192.168.1.1',
            'user_behavior'       => [
                'login_frequency'     => 'daily',
                'transaction_pattern' => 'regular',
                'account_age_days'    => 365,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'risk_score',
                    'risk_level',
                ],
            ]);
    }

    #[Test]
    public function test_calculate_risk_score_requires_authentication(): void
    {
        $response = $this->postJson('/api/risk/calculate', [
            'amount' => 1000.00,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_risk_factors_returns_list(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/risk/factors');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ])
            ->assertJson([
                'data' => [],
            ]);
    }

    #[Test]
    public function test_get_risk_factors_requires_authentication(): void
    {
        $response = $this->getJson('/api/risk/factors');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_risk_models_returns_available_models(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/risk/models');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ])
            ->assertJson([
                'data' => [],
            ]);
    }

    #[Test]
    public function test_get_risk_models_requires_authentication(): void
    {
        $response = $this->getJson('/api/risk/models');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_risk_history_returns_user_history(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $userId = 'user-789';
        $response = $this->getJson("/api/risk/users/{$userId}/history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'user_id',
                ],
            ])
            ->assertJson([
                'data' => [],
                'meta' => [
                    'user_id' => $userId,
                ],
            ]);
    }

    #[Test]
    public function test_get_risk_history_with_filters(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $userId = 'user-789';
        $response = $this->getJson("/api/risk/users/{$userId}/history?start_date=2024-01-01&end_date=2024-01-31");

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'user_id' => $userId,
                ],
            ]);
    }

    #[Test]
    public function test_get_risk_history_requires_authentication(): void
    {
        $response = $this->getJson('/api/risk/users/user-789/history');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_store_device_fingerprint_saves_device_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/risk/device-fingerprint', [
            'fingerprint'          => 'unique-fingerprint-123',
            'user_agent'           => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'screen_resolution'    => '1920x1080',
            'timezone'             => 'Europe/Berlin',
            'language'             => 'en-US',
            'platform'             => 'Windows',
            'hardware_concurrency' => 8,
            'device_memory'        => 8,
            'webgl_vendor'         => 'Intel Inc.',
            'webgl_renderer'       => 'Intel Iris Graphics',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Device fingerprint stored',
                'data'    => [],
            ]);
    }

    #[Test]
    public function test_store_device_fingerprint_with_minimal_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/risk/device-fingerprint', [
            'fingerprint' => 'minimal-fingerprint-456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Device fingerprint stored',
                'data'    => [],
            ]);
    }

    #[Test]
    public function test_store_device_fingerprint_requires_authentication(): void
    {
        $response = $this->postJson('/api/risk/device-fingerprint', [
            'fingerprint' => 'test-fingerprint',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_device_history_returns_user_devices(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $userId = 'user-999';
        $response = $this->getJson("/api/risk/users/{$userId}/devices");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'user_id',
                ],
            ])
            ->assertJson([
                'data' => [],
                'meta' => [
                    'user_id' => $userId,
                ],
            ]);
    }

    #[Test]
    public function test_get_device_history_requires_authentication(): void
    {
        $response = $this->getJson('/api/risk/users/user-999/devices');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_calculate_risk_score_with_high_risk_indicators(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/risk/calculate', [
            'amount'              => 100000.00,
            'currency'            => 'EUR',
            'user_id'             => $this->user->id,
            'transaction_type'    => 'withdrawal',
            'destination_country' => 'high_risk_country',
            'is_new_recipient'    => true,
            'is_unusual_time'     => true,
            'velocity_check'      => [
                'transactions_last_hour' => 10,
                'amount_last_24h'        => 150000,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'risk_score',
                    'risk_level',
                ],
            ]);
    }

    #[Test]
    public function test_analyze_transaction_with_context(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $transactionId = 'txn-complex-123';
        $response = $this->postJson("/api/risk/transactions/{$transactionId}/analyze", [
            'include_historical_analysis' => true,
            'include_peer_comparison'     => true,
            'include_ml_predictions'      => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'transaction_id',
                    'risk_score',
                    'risk_factors',
                ],
            ]);
    }

    #[Test]
    public function test_get_risk_history_with_pagination(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $userId = 'user-paginated';
        $response = $this->getJson("/api/risk/users/{$userId}/history?page=2&per_page=20");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'user_id',
                ],
            ]);
    }

    #[Test]
    public function test_get_device_history_with_suspicious_filter(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $userId = 'user-devices';
        $response = $this->getJson("/api/risk/users/{$userId}/devices?suspicious_only=true");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'user_id',
                ],
            ]);
    }

    #[Test]
    public function test_calculate_risk_score_for_merchant_transaction(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/risk/calculate', [
            'amount'              => 250.00,
            'currency'            => 'EUR',
            'user_id'             => $this->user->id,
            'transaction_type'    => 'merchant_payment',
            'merchant_id'         => 'merchant-123',
            'merchant_category'   => 'electronics',
            'merchant_risk_score' => 15,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'risk_score',
                    'risk_level',
                ],
            ]);
    }
}
