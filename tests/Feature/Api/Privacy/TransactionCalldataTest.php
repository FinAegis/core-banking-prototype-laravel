<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Privacy;

use App\Domain\Privacy\Models\PrivacyTransaction;
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

    public function test_transaction_calldata_returns_data_in_demo_mode(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/transaction-calldata/0xabc123');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'tx_hash',
                    'operation',
                    'token',
                    'amount',
                    'network',
                    'to_address',
                    'calldata',
                    'value',
                    'gas_estimate',
                    'status',
                    'recipient',
                    'created_at',
                ],
            ]);
    }

    public function test_transaction_calldata_route_exists(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/transaction-calldata/0x1234567890abcdef');

        $this->assertNotEquals(404, $response->status());
        $response->assertOk();
    }

    public function test_calldata_retrieved_by_tx_hash(): void
    {
        $txHash = '0x' . str_repeat('aa', 32);

        PrivacyTransaction::create([
            'user_id'    => $this->user->id,
            'tx_hash'    => $txHash,
            'operation'  => 'shield',
            'token'      => 'USDC',
            'amount'     => '100.00',
            'network'    => 'polygon',
            'to_address' => '0x' . str_repeat('ab', 20),
            'calldata'   => '0xdeadbeef',
            'value'      => '0',
            'status'     => PrivacyTransaction::STATUS_SUBMITTED,
        ]);

        // Force RAILGUN mode
        config(['privacy.zk.provider' => 'railgun']);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/privacy/transaction-calldata/{$txHash}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tx_hash', $txHash)
            ->assertJsonPath('data.operation', 'shield')
            ->assertJsonPath('data.token', 'USDC')
            ->assertJsonPath('data.amount', '100.00');
    }

    public function test_calldata_retrieved_by_uuid(): void
    {
        $tx = PrivacyTransaction::create([
            'user_id'    => $this->user->id,
            'operation'  => 'unshield',
            'token'      => 'USDT',
            'amount'     => '50.00',
            'network'    => 'ethereum',
            'to_address' => '0x' . str_repeat('cd', 20),
            'calldata'   => '0xcafebabe',
            'status'     => PrivacyTransaction::STATUS_PENDING,
            'recipient'  => '0x' . str_repeat('ef', 20),
        ]);

        config(['privacy.zk.provider' => 'railgun']);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/privacy/transaction-calldata/{$tx->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $tx->id)
            ->assertJsonPath('data.operation', 'unshield');
    }

    public function test_calldata_404_for_unknown_transaction(): void
    {
        config(['privacy.zk.provider' => 'railgun']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/transaction-calldata/0x' . str_repeat('ff', 32));

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Transaction not found.');
    }

    public function test_calldata_user_isolation(): void
    {
        $otherUser = User::factory()->create();
        $txHash = '0x' . str_repeat('bb', 32);

        PrivacyTransaction::create([
            'user_id'    => $otherUser->id,
            'tx_hash'    => $txHash,
            'operation'  => 'shield',
            'token'      => 'USDC',
            'amount'     => '200.00',
            'network'    => 'polygon',
            'to_address' => '0x' . str_repeat('ab', 20),
            'calldata'   => '0xdeadbeef',
            'status'     => PrivacyTransaction::STATUS_SUBMITTED,
        ]);

        config(['privacy.zk.provider' => 'railgun']);

        // Current user should NOT see other user's transaction
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/privacy/transaction-calldata/{$txHash}");

        $response->assertNotFound();
    }

    public function test_update_transaction_hash_requires_auth(): void
    {
        $response = $this->putJson('/api/v1/privacy/transactions/some-uuid/tx-hash', [
            'tx_hash' => '0x' . str_repeat('11', 32),
        ]);

        $response->assertUnauthorized();
    }

    public function test_update_transaction_hash_in_demo_mode(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v1/privacy/transactions/some-uuid/tx-hash', [
                'tx_hash' => '0x' . str_repeat('11', 32),
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Transaction hash updated.');
    }

    public function test_update_transaction_hash_in_railgun_mode(): void
    {
        $tx = PrivacyTransaction::create([
            'user_id'    => $this->user->id,
            'operation'  => 'shield',
            'token'      => 'USDC',
            'amount'     => '100.00',
            'network'    => 'polygon',
            'to_address' => '0x' . str_repeat('ab', 20),
            'calldata'   => '0xdeadbeef',
            'status'     => PrivacyTransaction::STATUS_PENDING,
        ]);

        config(['privacy.zk.provider' => 'railgun']);
        $newTxHash = '0x' . str_repeat('22', 32);

        $response = $this->withToken($this->token)
            ->putJson("/api/v1/privacy/transactions/{$tx->id}/tx-hash", [
                'tx_hash' => $newTxHash,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $tx->refresh();
        $this->assertSame($newTxHash, $tx->tx_hash);
        $this->assertSame(PrivacyTransaction::STATUS_SUBMITTED, $tx->status);
    }

    public function test_update_transaction_hash_404_for_unknown(): void
    {
        config(['privacy.zk.provider' => 'railgun']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/privacy/transactions/00000000-0000-0000-0000-000000000000/tx-hash', [
                'tx_hash' => '0x' . str_repeat('11', 32),
            ]);

        $response->assertNotFound();
    }

    public function test_update_transaction_hash_validates_input(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v1/privacy/transactions/some-uuid/tx-hash', []);

        $response->assertUnprocessable();
    }
}
