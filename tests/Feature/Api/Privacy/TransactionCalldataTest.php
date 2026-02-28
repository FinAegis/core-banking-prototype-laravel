<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Privacy;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TransactionCalldataTest extends TestCase
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

    public function test_transaction_calldata_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/privacy/transaction-calldata/0xabc123');

        $response->assertUnauthorized();
    }

    public function test_transaction_calldata_returns_501(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/transaction-calldata/0xabc123');

        $response->assertStatus(501)
            ->assertJsonPath('success', false)
            ->assertJsonPath('planned_version', 'v5.9.0')
            ->assertJsonStructure([
                'success',
                'error',
                'planned_version',
            ]);
    }

    public function test_transaction_calldata_route_exists(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/transaction-calldata/0x1234567890abcdef');

        // Should get 501, not 404
        $this->assertNotEquals(404, $response->status());
        $response->assertStatus(501);
    }
}
