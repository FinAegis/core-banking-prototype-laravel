<?php

declare(strict_types=1);

namespace Tests\Feature\Api\MobilePayment;

use App\Domain\Commerce\Enums\MerchantStatus;
use App\Domain\Commerce\Models\Merchant;
use App\Domain\MobilePayment\Enums\PaymentIntentStatus;
use App\Domain\MobilePayment\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentIntentControllerTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write', 'delete'])->plainTextToken;

        // Create a test merchant in the DB
        $this->merchant = Merchant::create([
            'public_id'         => 'merchant_test_' . Str::random(8),
            'display_name'      => 'Test Merchant',
            'icon_url'          => 'https://example.com/icon.png',
            'accepted_assets'   => ['USDC'],
            'accepted_networks' => ['SOLANA', 'TRON'],
            'status'            => MerchantStatus::ACTIVE,
        ]);
    }

    public function test_create_intent_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/payments/intents', [
            'merchantId'       => $this->merchant->public_id,
            'amount'           => '12.00',
            'asset'            => 'USDC',
            'preferredNetwork' => 'SOLANA',
        ]);

        $response->assertUnauthorized();
    }

    public function test_create_intent_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payments/intents', []);

        $response->assertUnprocessable();
    }

    public function test_create_intent_rejects_unsupported_asset(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payments/intents', [
                'merchantId'       => $this->merchant->public_id,
                'amount'           => '12.00',
                'asset'            => 'ETH',
                'preferredNetwork' => 'SOLANA',
            ]);

        $response->assertUnprocessable();
    }

    public function test_create_intent_rejects_unsupported_network(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payments/intents', [
                'merchantId'       => $this->merchant->public_id,
                'amount'           => '12.00',
                'asset'            => 'USDC',
                'preferredNetwork' => 'ETHEREUM',
            ]);

        $response->assertUnprocessable();
    }

    public function test_create_intent_rejects_zero_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payments/intents', [
                'merchantId'       => $this->merchant->public_id,
                'amount'           => '0',
                'asset'            => 'USDC',
                'preferredNetwork' => 'SOLANA',
            ]);

        $response->assertUnprocessable();
    }

    public function test_create_intent_returns_201_with_valid_data(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payments/intents', [
                'merchantId'       => $this->merchant->public_id,
                'amount'           => '12.00',
                'asset'            => 'USDC',
                'preferredNetwork' => 'SOLANA',
                'shield'           => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'intentId',
                    'merchantId',
                    'merchant' => ['displayName', 'iconUrl'],
                    'asset',
                    'network',
                    'amount',
                    'status',
                    'shieldEnabled',
                    'feesEstimate' => ['nativeAsset', 'amount', 'usdApprox'],
                    'createdAt',
                    'expiresAt',
                ],
            ])
            ->assertJsonPath('data.asset', 'USDC')
            ->assertJsonPath('data.network', 'SOLANA')
            ->assertJsonPath('data.status', 'AWAITING_AUTH')
            ->assertJsonPath('data.shieldEnabled', true);
    }

    public function test_create_intent_returns_404_for_unknown_merchant(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payments/intents', [
                'merchantId'       => 'nonexistent_merchant',
                'amount'           => '12.00',
                'asset'            => 'USDC',
                'preferredNetwork' => 'SOLANA',
            ]);

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_show_intent_returns_200(): void
    {
        $intent = PaymentIntent::create([
            'public_id'              => 'pi_' . Str::random(20),
            'user_id'                => $this->user->id,
            'merchant_id'            => $this->merchant->id,
            'asset'                  => 'USDC',
            'network'                => 'SOLANA',
            'amount'                 => '25.00',
            'status'                 => PaymentIntentStatus::AWAITING_AUTH,
            'shield_enabled'         => false,
            'fees_estimate'          => ['nativeAsset' => 'SOL', 'amount' => '0.00004', 'usdApprox' => '0.01'],
            'required_confirmations' => 32,
            'expires_at'             => now()->addMinutes(15),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/payments/intents/{$intent->public_id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intentId', $intent->public_id)
            ->assertJsonPath('data.status', 'AWAITING_AUTH');
    }

    public function test_show_intent_returns_404_for_other_users_intent(): void
    {
        $otherUser = User::factory()->create();

        $intent = PaymentIntent::create([
            'public_id'              => 'pi_' . Str::random(20),
            'user_id'                => $otherUser->id,
            'merchant_id'            => $this->merchant->id,
            'asset'                  => 'USDC',
            'network'                => 'SOLANA',
            'amount'                 => '25.00',
            'status'                 => PaymentIntentStatus::AWAITING_AUTH,
            'shield_enabled'         => false,
            'required_confirmations' => 32,
            'expires_at'             => now()->addMinutes(15),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/payments/intents/{$intent->public_id}");

        $response->assertNotFound();
    }

    public function test_submit_intent_returns_200(): void
    {
        $intent = PaymentIntent::create([
            'public_id'              => 'pi_' . Str::random(20),
            'user_id'                => $this->user->id,
            'merchant_id'            => $this->merchant->id,
            'asset'                  => 'USDC',
            'network'                => 'SOLANA',
            'amount'                 => '12.00',
            'status'                 => PaymentIntentStatus::AWAITING_AUTH,
            'shield_enabled'         => false,
            'fees_estimate'          => ['nativeAsset' => 'SOL', 'amount' => '0.00004', 'usdApprox' => '0.01'],
            'required_confirmations' => 32,
            'expires_at'             => now()->addMinutes(15),
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/payments/intents/{$intent->public_id}/submit", [
                'auth' => 'biometric',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // In demo mode, should have transitioned to PENDING with a tx_hash
        $intent->refresh();
        $this->assertEquals(PaymentIntentStatus::PENDING, $intent->status);
        $this->assertNotNull($intent->tx_hash);
    }

    public function test_submit_already_submitted_intent_returns_409(): void
    {
        $intent = PaymentIntent::create([
            'public_id'              => 'pi_' . Str::random(20),
            'user_id'                => $this->user->id,
            'merchant_id'            => $this->merchant->id,
            'asset'                  => 'USDC',
            'network'                => 'SOLANA',
            'amount'                 => '12.00',
            'status'                 => PaymentIntentStatus::PENDING,
            'shield_enabled'         => false,
            'required_confirmations' => 32,
            'expires_at'             => now()->addMinutes(15),
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/payments/intents/{$intent->public_id}/submit", [
                'auth' => 'biometric',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INTENT_ALREADY_SUBMITTED');
    }

    public function test_cancel_intent_returns_200(): void
    {
        $intent = PaymentIntent::create([
            'public_id'              => 'pi_' . Str::random(20),
            'user_id'                => $this->user->id,
            'merchant_id'            => $this->merchant->id,
            'asset'                  => 'USDC',
            'network'                => 'SOLANA',
            'amount'                 => '14.99',
            'status'                 => PaymentIntentStatus::AWAITING_AUTH,
            'shield_enabled'         => false,
            'required_confirmations' => 32,
            'expires_at'             => now()->addMinutes(15),
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/payments/intents/{$intent->public_id}/cancel", [
                'reason' => 'user_cancelled',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $intent->refresh();
        $this->assertEquals(PaymentIntentStatus::CANCELLED, $intent->status);
        $this->assertEquals('user_cancelled', $intent->cancel_reason);
    }

    public function test_cancel_submitted_intent_returns_409(): void
    {
        $intent = PaymentIntent::create([
            'public_id'              => 'pi_' . Str::random(20),
            'user_id'                => $this->user->id,
            'merchant_id'            => $this->merchant->id,
            'asset'                  => 'USDC',
            'network'                => 'SOLANA',
            'amount'                 => '14.99',
            'status'                 => PaymentIntentStatus::PENDING,
            'shield_enabled'         => false,
            'required_confirmations' => 32,
            'expires_at'             => now()->addMinutes(15),
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/payments/intents/{$intent->public_id}/cancel", [
                'reason' => 'user_cancelled',
            ]);

        $response->assertStatus(409);
    }
}
