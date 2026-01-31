<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class TransactionControllerTest extends ControllerTestCase
{
    protected User $user;

    protected User $otherUser;

    protected Account $account;

    protected Account $otherAccount;

    protected Asset $usdAsset;

    protected Asset $eurAsset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance'   => 100000, // 1000.00 USD
            'frozen'    => false,
        ]);

        $this->otherAccount = Account::factory()->create([
            'user_uuid' => $this->otherUser->uuid,
        ]);

        // Create assets
        $this->usdAsset = Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name'      => 'US Dollar',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );

        $this->eurAsset = Asset::firstOrCreate(
            ['code' => 'EUR'],
            [
                'name'      => 'Euro',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );

        // Create account balance for USD
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 100000, // $1000.00
        ]);
    }

    #[Test]
    public function test_deposit_usd_to_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'      => 250.50, // 250.50 USD
            'asset_code'  => 'USD',
            'description' => 'Cash deposit',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Deposit initiated successfully',
            ]);
    }

    #[Test]
    public function test_deposit_eur_to_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'      => 100.00, // 100.00 EUR
            'asset_code'  => 'EUR',
            'description' => 'EUR deposit',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Deposit initiated successfully',
            ]);
    }

    #[Test]
    public function test_deposit_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'asset_code']);
    }

    #[Test]
    public function test_deposit_validates_minimum_amount(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'     => 0,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    #[Test]
    public function test_deposit_validates_asset_exists(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'     => 100,
            'asset_code' => 'INVALID',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['asset_code']);
    }

    #[Test]
    public function test_deposit_prevents_access_to_other_users_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->otherAccount->uuid}/deposit", [
            'amount'     => 100,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied to this account',
                'error'   => 'FORBIDDEN',
            ]);
    }

    #[Test]
    public function test_deposit_prevents_deposit_to_frozen_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $frozenAccount = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => true,
        ]);

        $response = $this->postJson("/api/accounts/{$frozenAccount->uuid}/deposit", [
            'amount'     => 100,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot deposit to frozen account',
                'error'   => 'ACCOUNT_FROZEN',
            ]);
    }

    #[Test]
    public function test_deposit_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/accounts/non-existent-uuid/deposit', [
            'amount'     => 100,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function test_deposit_requires_authentication(): void
    {
        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'     => 100,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_withdraw_usd_from_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", [
            'amount'      => 100.00, // 100.00 USD
            'asset_code'  => 'USD',
            'description' => 'ATM withdrawal',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Withdrawal initiated successfully',
            ]);
    }

    #[Test]
    public function test_withdraw_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'asset_code']);
    }

    #[Test]
    public function test_withdraw_validates_minimum_amount(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", [
            'amount'     => 0,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    #[Test]
    public function test_withdraw_prevents_insufficient_balance(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", [
            'amount'     => 2000.00, // More than balance
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Insufficient balance',
                'errors'  => [
                    'amount' => ['Insufficient balance'],
                ],
            ]);
    }

    #[Test]
    public function test_withdraw_prevents_access_to_other_users_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->otherAccount->uuid}/withdraw", [
            'amount'     => 100,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied to this account',
                'error'   => 'FORBIDDEN',
            ]);
    }

    #[Test]
    public function test_withdraw_prevents_withdrawal_from_frozen_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $frozenAccount = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance'   => 50000,
            'frozen'    => true,
        ]);

        $response = $this->postJson("/api/accounts/{$frozenAccount->uuid}/withdraw", [
            'amount'     => 100,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot withdraw from frozen account',
                'error'   => 'ACCOUNT_FROZEN',
            ]);
    }

    #[Test]
    public function test_withdraw_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/accounts/non-existent-uuid/withdraw', [
            'amount'     => 100,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function test_withdraw_requires_authentication(): void
    {
        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", [
            'amount'     => 100,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_history_returns_transaction_list(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'account_uuid',
                        'type',
                        'amount',
                        'asset_code',
                        'description',
                        'hash',
                        'created_at',
                        'metadata',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'account_uuid',
                ],
            ]);
    }

    #[Test]
    public function test_history_filters_by_transaction_type(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions?type=credit");

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $transaction) {
            $this->assertEquals('credit', $transaction['type']);
        }
    }

    #[Test]
    public function test_history_filters_by_asset_code(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions?asset_code=EUR");

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $transaction) {
            $this->assertEquals('EUR', $transaction['asset_code']);
        }
    }

    #[Test]
    public function test_history_paginates_results(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions?per_page=10");

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10);
    }

    #[Test]
    public function test_history_validates_per_page_limits(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions?per_page=101");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    #[Test]
    public function test_history_validates_type_parameter(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions?type=invalid");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function test_history_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/accounts/non-existent-uuid/transactions');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_history_requires_authentication(): void
    {
        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions");

        $response->assertStatus(401);
    }

    #[Test]
    public function test_deposit_with_long_description(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'      => 100,
            'asset_code'  => 'USD',
            'description' => str_repeat('a', 256), // Too long
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    #[Test]
    public function test_withdraw_with_valid_description(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", [
            'amount'      => 50.00,
            'asset_code'  => 'USD',
            'description' => 'Monthly bill payment',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Withdrawal initiated successfully',
            ]);
    }
}
