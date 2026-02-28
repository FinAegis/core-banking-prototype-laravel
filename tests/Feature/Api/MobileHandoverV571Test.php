<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Commerce\Models\Merchant;
use App\Domain\MobilePayment\Enums\PaymentIntentStatus;
use App\Domain\MobilePayment\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Tests for v5.7.1 mobile handover fixes.
 * Covers: #2 broadcasting auth, #4 recipient name, #7 card transactions.
 */
class MobileHandoverV571Test extends TestCase
{
    protected User $user;

    protected string $token;

    protected Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create(['name' => 'Test User']);
        $this->token = $this->user->createToken('test-token', ['read', 'write'])->plainTextToken;
        $this->merchant = Merchant::create([
            'public_id'         => 'merch_test_' . bin2hex(random_bytes(4)),
            'display_name'      => 'Test Merchant',
            'accepted_assets'   => ['USDC'],
            'accepted_networks' => ['polygon'],
            'status'            => 'active',
        ]);
    }

    // --- #2: Broadcasting Auth ---

    public function test_broadcasting_auth_endpoint_exists(): void
    {
        // Without authentication, should get 403 or 401 (not 404)
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id'    => '1234.5678',
            'channel_name' => 'private-App.Models.User.1',
        ]);

        // The key assertion: NOT a 404 (endpoint exists)
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_broadcasting_auth_with_authenticated_user(): void
    {
        // With authentication, should not return 404 (endpoint exists and processes the request)
        $response = $this->withToken($this->token)
            ->postJson('/broadcasting/auth', [
                'socket_id'    => '1234.5678',
                'channel_name' => 'private-App.Models.User.' . $this->user->id,
            ]);

        // Should not be 404 (endpoint exists)
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    // --- #4: Recent Recipients Name Field ---

    public function test_recent_recipients_returns_name_field(): void
    {
        // Create a recipient user with a blockchain address
        $recipient = User::factory()->create(['name' => 'Alice Johnson']);
        BlockchainAddress::create([
            'user_uuid'       => $recipient->uuid,
            'chain'           => 'polygon',
            'address'         => '0xRecipientAddress123',
            'public_key'      => 'pk_test',
            'derivation_path' => "m/44'/60'/0'/0/0",
            'label'           => 'Main',
            'is_active'       => true,
        ]);

        // Create a payment intent from our user to the recipient
        PaymentIntent::create([
            'public_id'   => 'pi_test_' . bin2hex(random_bytes(8)),
            'user_id'     => $this->user->id,
            'merchant_id' => $this->merchant->id,
            'amount'      => '10.00',
            'asset'       => 'USDC',
            'network'     => 'polygon',
            'status'      => PaymentIntentStatus::CONFIRMED,
            'metadata'    => ['recipient_address' => '0xRecipientAddress123'],
            'expires_at'  => now()->addHour(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/wallet/recent-recipients');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertEquals('Alice Johnson', $data[0]['name']);
        $this->assertEquals('0xRecipientAddress123', $data[0]['address']);
    }

    public function test_recent_recipients_returns_null_name_for_unknown_address(): void
    {
        // Create a payment intent to an unknown address (no matching BlockchainAddress)
        PaymentIntent::create([
            'public_id'   => 'pi_test_' . bin2hex(random_bytes(8)),
            'user_id'     => $this->user->id,
            'merchant_id' => $this->merchant->id,
            'amount'      => '5.00',
            'asset'       => 'USDC',
            'network'     => 'polygon',
            'status'      => PaymentIntentStatus::CONFIRMED,
            'metadata'    => ['recipient_address' => '0xUnknownAddress999'],
            'expires_at'  => now()->addHour(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/wallet/recent-recipients');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertNull($data[0]['name']);
    }

    // --- #7: Card Transactions Returns Demo Data (v5.8.0) ---

    public function test_card_transactions_returns_demo_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cards/test-card-123/transactions');

        $response->assertOk();
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['data']);
        $this->assertArrayHasKey('id', $data['data'][0]);
        $this->assertArrayHasKey('amount', $data['data'][0]);
        $this->assertArrayHasKey('merchant', $data['data'][0]);
    }
}
