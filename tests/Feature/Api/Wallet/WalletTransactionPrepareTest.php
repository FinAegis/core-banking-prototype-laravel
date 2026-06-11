<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Wallet;

use App\Domain\Wallet\Services\SponsorshipCostTracker;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression coverage for the prepare endpoint's `network` validator.
 *
 * The quote endpoint accepts `network=SOLANA` because it validates against
 * `PaymentNetwork::values()`. Prepare used to be hardcoded
 * `in:solana,polygon,base,arbitrum,ethereum` which both rejected the
 * canonical `SOLANA` casing AND accepted strings that aren't valid enum
 * values. These tests lock in the post-fix contract: both endpoints share
 * the same case-sensitive `PaymentNetwork` allow-list.
 *
 * We only assert the *validator* — the full prepare flow needs a registered
 * sender address + Pimlico paymaster, etc. `assertJsonMissingValidationErrors`
 * is enough to prove the network rule accepts/rejects what it should.
 */
class WalletTransactionPrepareTest extends TestCase
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

    public function test_prepare_accepts_network_solana_uppercase(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/prepare', [
                'to'      => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
                'token'   => 'USDC',
                'amount'  => '1.50',
                'network' => 'SOLANA',
                'quoteId' => 'q_test_solana_upper',
            ]);

        // Response may be 422 for downstream reasons (no sender address,
        // missing paymaster, etc.) — we only care that the `network` field
        // itself is not the reason.
        $response->assertJsonMissingValidationErrors(['network']);
    }

    public function test_prepare_accepts_network_polygon_lowercase(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/prepare', [
                'to'      => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
                'token'   => 'USDC',
                'amount'  => '1.50',
                'network' => 'polygon',
                'quoteId' => 'q_test_polygon_lower',
            ]);

        $response->assertJsonMissingValidationErrors(['network']);
    }

    public function test_prepare_rejects_network_solana_lowercase(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/prepare', [
                'to'      => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
                'token'   => 'USDC',
                'amount'  => '1.50',
                'network' => 'solana',
                'quoteId' => 'q_test_solana_lower',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['network']);
    }

    public function test_prepare_rejects_network_ethereum_uppercase(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/prepare', [
                'to'      => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
                'token'   => 'USDC',
                'amount'  => '1.50',
                'network' => 'ETHEREUM',
                'quoteId' => 'q_test_ethereum_upper',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['network']);
    }

    public function test_prepare_rejects_unknown_network(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/prepare', [
                'to'      => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
                'token'   => 'USDC',
                'amount'  => '1.50',
                'network' => 'FOOBAR',
                'quoteId' => 'q_test_foobar',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['network']);
    }

    public function test_prepare_accepts_quote_id_snake_case(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/prepare', [
                'to'       => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
                'token'    => 'USDC',
                'amount'   => '1.50',
                'network'  => 'polygon',
                'quote_id' => 'q_test_snake',
            ]);

        $response->assertJsonMissingValidationErrors(['quote_id', 'quoteId']);
    }

    public function test_prepare_accepts_quote_id_camel_case(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/prepare', [
                'to'      => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
                'token'   => 'USDC',
                'amount'  => '1.50',
                'network' => 'polygon',
                'quoteId' => 'q_test_camel',
            ]);

        $response->assertJsonMissingValidationErrors(['quote_id', 'quoteId']);
    }

    public function test_prepare_rejects_missing_quote_id_with_snake_case_key(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/prepare', [
                'to'      => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
                'token'   => 'USDC',
                'amount'  => '1.50',
                'network' => 'polygon',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quote_id']);
    }

    public function test_prepare_rejects_ethereum_l1_send(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/transactions/prepare', [
                'to'       => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
                'token'    => 'USDC',
                'amount'   => '1.50',
                'network'  => 'ethereum',
                'quote_id' => 'q_test_eth_l1',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'NETWORK_DISABLED');
    }

    public function test_prepare_blocks_after_per_user_daily_limit(): void
    {
        config(['wallet.sponsorship.per_user_daily_limit' => 2]);

        $body = [
            'to'       => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
            'token'    => 'USDC',
            'amount'   => '1.50',
            'network'  => 'polygon',
            'quote_id' => 'q_test_ratelimit',
        ];

        // First two prepares clear the guardrail — they fail later for an
        // unrelated reason (no registered sender address), which is not a 429.
        $this->withToken($this->token)->postJson('/api/v1/wallet/transactions/prepare', $body)
            ->assertStatus(422)->assertJsonPath('error.code', 'NO_SENDER_ADDRESS');
        $this->withToken($this->token)->postJson('/api/v1/wallet/transactions/prepare', $body)
            ->assertStatus(422)->assertJsonPath('error.code', 'NO_SENDER_ADDRESS');

        // Third trips the per-user daily limit.
        $this->withToken($this->token)->postJson('/api/v1/wallet/transactions/prepare', $body)
            ->assertStatus(429)->assertJsonPath('error.code', 'SEND_DAILY_LIMIT_REACHED');
    }

    public function test_prepare_blocks_when_global_daily_ceiling_reached(): void
    {
        config(['wallet.sponsorship.global_daily_limit' => 1]);

        $body = [
            'to'       => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
            'token'    => 'USDC',
            'amount'   => '1.50',
            'network'  => 'polygon',
            'quote_id' => 'q_test_global',
        ];

        $this->withToken($this->token)->postJson('/api/v1/wallet/transactions/prepare', $body)
            ->assertStatus(422)->assertJsonPath('error.code', 'NO_SENDER_ADDRESS');

        $this->withToken($this->token)->postJson('/api/v1/wallet/transactions/prepare', $body)
            ->assertStatus(429)->assertJsonPath('error.code', 'SEND_TEMPORARILY_UNAVAILABLE');
    }

    public function test_prepare_blocks_when_daily_usd_budget_is_exhausted(): void
    {
        config(['wallet.sponsorship.daily_budget_usd' => '10']);

        // Simulate $10.000000 of sponsored spend accumulated by confirmations.
        $key = SponsorshipCostTracker::budgetCacheKey();
        Cache::add($key, 0, now()->addDay());
        Cache::increment($key, 10_000_000);

        $body = [
            'to'       => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
            'token'    => 'USDC',
            'amount'   => '1.50',
            'network'  => 'polygon',
            'quote_id' => 'q_test_budget',
        ];

        // Same 429 shape as the global count ceiling.
        $this->withToken($this->token)->postJson('/api/v1/wallet/transactions/prepare', $body)
            ->assertStatus(429)->assertJsonPath('error.code', 'SEND_TEMPORARILY_UNAVAILABLE');
    }

    public function test_prepare_ignores_spend_counter_when_no_budget_is_configured(): void
    {
        config(['wallet.sponsorship.daily_budget_usd' => null]);

        // Massive accumulated spend, but no budget configured → no behavior change.
        $key = SponsorshipCostTracker::budgetCacheKey();
        Cache::add($key, 0, now()->addDay());
        Cache::increment($key, 999_999_999);

        $body = [
            'to'       => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
            'token'    => 'USDC',
            'amount'   => '1.50',
            'network'  => 'polygon',
            'quote_id' => 'q_test_no_budget',
        ];

        // Fails later for an unrelated reason (no sender address) — not a 429.
        $this->withToken($this->token)->postJson('/api/v1/wallet/transactions/prepare', $body)
            ->assertStatus(422)->assertJsonPath('error.code', 'NO_SENDER_ADDRESS');
    }
}
