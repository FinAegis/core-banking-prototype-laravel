<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Wallet;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression coverage for the submit endpoint's `intent_id` field name.
 *
 * The whole API is snake_case, but submit used to validate `intentId` only.
 * These tests lock in the post-fix contract: the canonical key is snake_case
 * (`intent_id`) and the legacy camelCase (`intentId`) is still accepted.
 *
 * We only assert the *validator*. When the field is present and valid the
 * request gets past validation and 404s on INTENT_NOT_FOUND (no record) —
 * `assertJsonMissingValidationErrors` is enough to prove acceptance.
 */
class WalletTransactionSubmitTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write', 'delete'])->plainTextToken;
    }

    public function test_submit_accepts_intent_id_snake_case(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/submit', [
                'intent_id' => 'pi_send_unknown',
                'signature' => '0xdeadbeef',
            ]);

        $response->assertJsonMissingValidationErrors(['intent_id', 'intentId']);
    }

    public function test_submit_accepts_intent_id_camel_case(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/submit', [
                'intentId'  => 'pi_send_unknown',
                'signature' => '0xdeadbeef',
            ]);

        $response->assertJsonMissingValidationErrors(['intent_id', 'intentId']);
    }

    public function test_submit_rejects_missing_intent_id_with_snake_case_key(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/submit', [
                'signature' => '0xdeadbeef',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['intent_id']);
    }

    public function test_a_second_submit_while_the_first_is_running_is_refused(): void
    {
        $record = \App\Domain\Wallet\Models\WalletSendRecord::create([
            'public_id'         => 'pi_send_concurrent_test',
            'user_id'           => $this->user->id,
            'network'           => 'polygon',
            'asset'             => 'USDC',
            'amount'            => '1.00000000',
            'sender_address'    => '0x1111111111111111111111111111111111111111',
            'recipient_address' => '0x2222222222222222222222222222222222222222',
            'status'            => 'pending',
            'user_op_hash'      => '0x' . str_repeat('ab', 32),
            'metadata'          => ['user_op' => ['sender' => '0x1111111111111111111111111111111111111111']],
        ]);

        $held = Cache::lock('wallet:send:submit:' . $record->id, 120);
        $this->assertTrue($held->get());

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/submit', [
                'intent_id' => 'pi_send_concurrent_test',
                'signature' => '0xdeadbeef',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'SUBMIT_IN_PROGRESS');
        $this->assertSame('pending', $record->fresh()?->status);

        $held->release();
    }
}
