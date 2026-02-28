<?php

declare(strict_types=1);

namespace Tests\Feature\Api\CardIssuance;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CardDetailTest extends TestCase
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

    public function test_store_card_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/cards');

        $response->assertUnauthorized();
    }

    public function test_store_card_creates_new_card(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cards', [
                'cardholder_name' => 'Test User',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['card_token', 'last4', 'network', 'status', 'label'],
            ]);
    }

    public function test_store_card_with_network_mastercard(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cards', [
                'cardholder_name' => 'Test User',
                'network'         => 'mastercard',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.network', 'mastercard');
    }

    public function test_store_card_with_label(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cards', [
                'cardholder_name' => 'Test User',
                'label'           => 'My Travel Card',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.label', 'My Travel Card');
    }

    public function test_store_card_defaults_to_visa_without_network(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cards', [
                'cardholder_name' => 'Test User',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.network', 'visa');
    }

    public function test_store_card_rejects_invalid_network(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cards', [
                'cardholder_name' => 'Test User',
                'network'         => 'amex',
            ]);

        $response->assertUnprocessable();
    }

    public function test_show_card_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/cards/test-card-id');

        $response->assertUnauthorized();
    }

    public function test_card_transactions_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/cards/test-card-id/transactions');

        $response->assertUnauthorized();
    }

    public function test_card_transactions_returns_demo_data(): void
    {
        // Create a card first so we have a valid card ID
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v1/cards', [
                'cardholder_name' => 'Test User',
            ]);

        $cardToken = $createResponse->json('data.card_token');

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/cards/{$cardToken}/transactions");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'amount', 'currency', 'merchant', 'category', 'status', 'timestamp'],
                ],
                'pagination' => ['next_cursor', 'has_more', 'total'],
            ]);

        // Demo adapter returns 5 deterministic transactions
        $data = $response->json('data');
        $this->assertCount(5, $data);
        $this->assertEquals('Starbucks', $data[0]['merchant']);
        $this->assertEquals(4.75, $data[0]['amount']);
        $this->assertEquals('USD', $data[0]['currency']);
    }

    public function test_card_transactions_respects_limit(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v1/cards', [
                'cardholder_name' => 'Test User',
            ]);

        $cardToken = $createResponse->json('data.card_token');

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/cards/{$cardToken}/transactions?limit=2");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertTrue($response->json('pagination.has_more'));
        $this->assertNotNull($response->json('pagination.next_cursor'));
    }

    public function test_card_transactions_cursor_pagination(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v1/cards', [
                'cardholder_name' => 'Test User',
            ]);

        $cardToken = $createResponse->json('data.card_token');

        // Get first page
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/cards/{$cardToken}/transactions?limit=3");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
        $cursor = $response->json('pagination.next_cursor');

        // Get second page
        $response2 = $this->withToken($this->token)
            ->getJson("/api/v1/cards/{$cardToken}/transactions?limit=3&cursor={$cursor}");

        $response2->assertOk();
        $this->assertCount(2, $response2->json('data'));
        $this->assertFalse($response2->json('pagination.has_more'));
    }

    public function test_cancel_card_requires_biometric_token(): void
    {
        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/cards/some-card-id', [
                'reason' => 'No longer needed',
            ]);

        $response->assertUnprocessable();
    }

    public function test_cancel_card_rejects_invalid_biometric_token(): void
    {
        // Create a card first
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v1/cards', [
                'cardholder_name' => 'Test User',
            ]);

        $cardToken = $createResponse->json('data.card_token');

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v1/cards/{$cardToken}", [
                'biometric_token' => str_repeat('a', 64),
                'reason'          => 'No longer needed',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_BIOMETRIC_001');
    }

    public function test_cancel_card_with_valid_biometric_token(): void
    {
        // Create a card first
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v1/cards', [
                'cardholder_name' => 'Test User',
            ]);

        $cardToken = $createResponse->json('data.card_token');

        // Generate valid demo HMAC token
        $biometricToken = hash_hmac('sha256', 'demo_biometric:' . $this->user->id, (string) config('app.key'));

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v1/cards/{$cardToken}", [
                'biometric_token' => $biometricToken,
                'reason'          => 'No longer needed',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Card cancelled successfully');
    }
}
