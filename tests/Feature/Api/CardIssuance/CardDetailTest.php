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
                'data' => ['card_token', 'last4', 'network', 'status'],
            ]);
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

    public function test_card_transactions_returns_empty_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cards/test-card-id/transactions');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }
}
