<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Commerce;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MobileCommerceDetailTest extends TestCase
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

    public function test_merchant_detail_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/commerce/merchants/merchant_demo_001');

        $response->assertUnauthorized();
    }

    public function test_merchant_detail_returns_existing_merchant(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/commerce/merchants/merchant_demo_001');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', 'merchant_demo_001')
            ->assertJsonPath('data.display_name', 'Demo Coffee Shop');
    }

    public function test_merchant_detail_returns_404_for_nonexistent(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/commerce/merchants/nonexistent');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_payment_request_detail_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/commerce/payment-requests/pr_test_123');

        $response->assertUnauthorized();
    }

    public function test_payment_request_detail_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/commerce/payment-requests/pr_test_123');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', 'pr_test_123')
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'merchant_id', 'amount', 'asset', 'network', 'status'],
            ]);
    }

    public function test_cancel_payment_request_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/commerce/payment-requests/pr_test_123/cancel');

        $response->assertUnauthorized();
    }

    public function test_cancel_payment_request_returns_cancelled_status(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/commerce/payment-requests/pr_test_123/cancel');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_recent_payments_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/commerce/payments/recent');

        $response->assertUnauthorized();
    }

    public function test_recent_payments_returns_empty_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/commerce/payments/recent');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }
}
