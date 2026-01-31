<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class TransferControllerTest extends ControllerTestCase
{
    protected User $user;

    protected User $otherUser;

    protected Account $fromAccount;

    protected Account $toAccount;

    protected Asset $usdAsset;

    protected Asset $eurAsset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->fromAccount = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => false,
        ]);

        $this->toAccount = Account::factory()->create([
            'user_uuid' => $this->otherUser->uuid,
            'frozen'    => false,
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

        // Create USD balances for the accounts
        AccountBalance::create([
            'account_uuid' => $this->fromAccount->uuid,
            'asset_id'     => $this->usdAsset->id,
            'asset_code'   => 'USD',
            'balance'      => 100000, // 1000.00 USD
        ]);

        AccountBalance::create([
            'account_uuid' => $this->toAccount->uuid,
            'asset_id'     => $this->usdAsset->id,
            'asset_code'   => 'USD',
            'balance'      => 0, // 0 USD
        ]);
    }

    #[Test]
    public function test_store_creates_transfer_with_valid_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid'   => $this->toAccount->uuid,
            'amount'            => 100.00, // 100.00 USD
            'asset_code'        => 'USD',
            'description'       => 'Payment for services',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'status',
                    'from_account',
                    'to_account',
                    'amount',
                    'asset_code',
                    'reference',
                    'created_at',
                ],
                'message',
            ])
            ->assertJson([
                'data' => [
                    'status'       => 'pending',
                    'from_account' => $this->fromAccount->uuid,
                    'to_account'   => $this->toAccount->uuid,
                    'amount'       => 100.00,
                    'asset_code'   => 'USD',
                    'reference'    => 'Payment for services',
                ],
                'message' => 'Transfer initiated successfully',
            ]);
    }

    #[Test]
    public function test_store_supports_legacy_field_names(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/transfers', [
            'from_account' => $this->fromAccount->uuid,
            'to_account'   => $this->toAccount->uuid,
            'amount'       => 50.00,
            'asset_code'   => 'USD',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.from_account', (string) $this->fromAccount->uuid)
            ->assertJsonPath('data.to_account', (string) $this->toAccount->uuid);
    }

    #[Test]
    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/transfers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'asset_code']);
    }

    #[Test]
    public function test_store_requires_both_account_uuids(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => $this->fromAccount->uuid,
            'amount'            => 100,
            'asset_code'        => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Both from and to account UUIDs are required',
                'errors'  => [
                    'to_account_uuid' => ['The to account uuid field is required.'],
                ],
            ]);
    }

    #[Test]
    public function test_store_validates_minimum_amount(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid'   => $this->toAccount->uuid,
            'amount'            => 0,
            'asset_code'        => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    #[Test]
    public function test_store_prevents_self_transfer(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid'   => $this->fromAccount->uuid, // Same account
            'amount'            => 100,
            'asset_code'        => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_account_uuid']);
    }

    #[Test]
    public function test_store_validates_asset_exists(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid'   => $this->toAccount->uuid,
            'amount'            => 100,
            'asset_code'        => 'INVALID',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['asset_code']);
    }

    #[Test]
    public function test_store_prevents_transfer_from_frozen_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $frozenAccount = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => true,
        ]);

        // Create USD balance for the frozen account
        AccountBalance::create([
            'account_uuid' => $frozenAccount->uuid,
            'asset_id'     => $this->usdAsset->id,
            'asset_code'   => 'USD',
            'balance'      => 50000, // 500.00 USD
        ]);

        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => $frozenAccount->uuid,
            'to_account_uuid'   => $this->toAccount->uuid,
            'amount'            => 100,
            'asset_code'        => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot transfer from frozen account',
                'error'   => 'SOURCE_ACCOUNT_FROZEN',
            ]);
    }

    #[Test]
    public function test_store_prevents_transfer_to_frozen_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $frozenAccount = Account::factory()->create([
            'user_uuid' => $this->otherUser->uuid,
            'frozen'    => true,
        ]);

        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid'   => $frozenAccount->uuid,
            'amount'            => 100,
            'asset_code'        => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot transfer to frozen account',
                'error'   => 'DESTINATION_ACCOUNT_FROZEN',
            ]);
    }

    #[Test]
    public function test_store_prevents_insufficient_funds(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid'   => $this->toAccount->uuid,
            'amount'            => 2000.00, // More than balance
            'asset_code'        => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Insufficient funds',
                'error'   => 'INSUFFICIENT_FUNDS',
            ])
            ->assertJsonStructure([
                'current_balance',
                'requested_amount',
            ]);
    }

    #[Test]
    public function test_store_validates_account_exists(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => 'non-existent-uuid',
            'to_account_uuid'   => $this->toAccount->uuid,
            'amount'            => 100,
            'asset_code'        => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['from_account_uuid']);
    }

    #[Test]
    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid'   => $this->toAccount->uuid,
            'amount'            => 100,
            'asset_code'        => 'USD',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_show_returns_404_for_non_existent_transfer(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/transfers/non-existent-uuid');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_show_requires_authentication(): void
    {
        $response = $this->getJson('/api/transfers/some-uuid');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_history_returns_transfer_list(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson("/api/accounts/{$this->fromAccount->uuid}/transfers");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'from_account_uuid',
                        'to_account_uuid',
                        'amount',
                        'direction',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    #[Test]
    public function test_history_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/accounts/non-existent-uuid/transfers');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_history_requires_authentication(): void
    {
        $response = $this->getJson("/api/accounts/{$this->fromAccount->uuid}/transfers");

        $response->assertStatus(401);
    }

    #[Test]
    public function test_store_with_reference_field(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid'   => $this->toAccount->uuid,
            'amount'            => 75.50,
            'asset_code'        => 'USD',
            'reference'         => 'INV-2024-001',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reference', 'INV-2024-001');
    }

    #[Test]
    public function test_store_validates_field_lengths(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid'   => $this->toAccount->uuid,
            'amount'            => 100,
            'asset_code'        => 'USD',
            'reference'         => str_repeat('a', 256), // Too long
            'description'       => str_repeat('b', 256), // Too long
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reference', 'description']);
    }

    #[Test]
    public function test_store_with_eur_transfer(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        // Create an account with EUR balance
        $eurAccount = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'frozen'    => false,
        ]);

        // Create EUR balance using AccountBalance model
        AccountBalance::create([
            'account_uuid' => $eurAccount->uuid,
            'asset_id'     => $this->eurAsset->id,
            'asset_code'   => 'EUR',
            'balance'      => 50000, // 500.00 EUR
        ]);

        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => $eurAccount->uuid,
            'to_account_uuid'   => $this->toAccount->uuid,
            'amount'            => 200.00, // 200.00 EUR
            'asset_code'        => 'EUR',
            'description'       => 'EUR transfer',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.asset_code', 'EUR')
            ->assertJsonPath('data.amount', 200);
    }
}
