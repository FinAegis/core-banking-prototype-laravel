<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Wallet;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WalletTransactionQuoteTest extends TestCase
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

    public function test_transaction_quote_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/wallet/transactions/quote', [
            'to'      => 'TN5MbLVYcSGdoGodXMReZyzQn3sLFVoJkm',
            'network' => 'TRON',
            'asset'   => 'USDC',
            'amount'  => 100,
        ]);

        $response->assertUnauthorized();
    }

    public function test_transaction_quote_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/quote', []);

        $response->assertUnprocessable();
    }

    public function test_transaction_quote_returns_quote_with_valid_input(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/quote', [
                'to'      => 'TN5MbLVYcSGdoGodXMReZyzQn3sLFVoJkm',
                'network' => 'TRON',
                'asset'   => 'USDC',
                'amount'  => 100,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'quote_id',
                    'to',
                    'amount',
                    'asset',
                    'network',
                    'fee',
                    'total',
                    'expires_at',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.asset', 'USDC')
            ->assertJsonPath('data.network', 'TRON');
    }

    public function test_transaction_quote_fails_with_invalid_address(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/quote', [
                'to'      => 'invalid_address',
                'network' => 'TRON',
                'asset'   => 'USDC',
                'amount'  => 100,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }
}
