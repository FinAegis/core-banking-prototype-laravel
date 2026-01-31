<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class AccountBalanceControllerTest extends ControllerTestCase
{
    protected User $user;

    protected Account $account;

    protected Asset $usdAsset;

    protected Asset $eurAsset;

    protected Asset $btcAsset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
        ]);

        // Create assets
        $this->usdAsset = Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name'         => 'US Dollar',
                'type'         => 'fiat',
                'precision'    => 2,
                'is_active'    => true,
                'is_tradeable' => true,
                'symbol'       => '$',
                'metadata'     => ['symbol' => '$'],
            ]
        );

        $this->eurAsset = Asset::firstOrCreate(
            ['code' => 'EUR'],
            [
                'name'         => 'Euro',
                'type'         => 'fiat',
                'precision'    => 2,
                'is_active'    => true,
                'is_tradeable' => true,
                'symbol'       => '€',
                'metadata'     => ['symbol' => '€'],
            ]
        );

        $this->btcAsset = Asset::firstOrCreate(
            ['code' => 'BTC'],
            [
                'name'         => 'Bitcoin',
                'type'         => 'crypto',
                'precision'    => 8,
                'is_active'    => true,
                'is_tradeable' => true,
                'symbol'       => '₿',
                'metadata'     => ['symbol' => '₿'],
            ]
        );
    }

    #[Test]
    public function test_show_returns_all_account_balances(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        // Create balances for the account
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000, // $500.00
        ]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'EUR',
            'balance'      => 30000, // €300.00
        ]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'BTC',
            'balance'      => 10000000, // 0.1 BTC
        ]);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balances");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'account_uuid',
                    'balances' => [
                        '*' => [
                            'asset_code',
                            'balance',
                            'formatted',
                            'asset' => [
                                'code',
                                'name',
                                'type',
                                'symbol',
                                'precision',
                            ],
                        ],
                    ],
                    'summary' => [
                        'total_assets',
                        'total_usd_equivalent',
                    ],
                ],
            ])
            ->assertJsonPath('data.account_uuid', (string) $this->account->uuid)
            ->assertJsonCount(3, 'data.balances');
    }

    #[Test]
    public function test_show_filters_by_asset_code(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000,
        ]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'EUR',
            'balance'      => 30000,
        ]);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balances?asset=USD");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.balances')
            ->assertJsonPath('data.balances.0.asset_code', 'USD');
    }

    #[Test]
    public function test_show_filters_positive_balances_only(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000,
        ]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'EUR',
            'balance'      => 0,
        ]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'BTC',
            'balance'      => 10000000,
        ]);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balances?positive=true");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.balances');

        $balances = collect($response->json('data.balances'));
        $this->assertTrue($balances->every(fn ($balance) => $balance['balance'] > 0));
    }

    #[Test]
    public function test_show_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/accounts/non-existent-uuid/balances');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Account not found',
                'error'   => 'The specified account UUID was not found',
            ]);
    }

    #[Test]
    public function test_show_formats_balances_correctly(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 123456, // $1234.56
        ]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'BTC',
            'balance'      => 12345678, // 0.12345678 BTC
        ]);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balances");

        $response->assertStatus(200);

        $balances = collect($response->json('data.balances'));
        $usdBalance = $balances->firstWhere('asset_code', 'USD');
        $btcBalance = $balances->firstWhere('asset_code', 'BTC');

        $this->assertEquals('1234.56 USD', $usdBalance['formatted']);
        $this->assertEquals('0.12345678 BTC', $btcBalance['formatted']);
    }

    #[Test]
    public function test_show_calculates_usd_equivalent(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 100000, // $1000.00
        ]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'EUR',
            'balance'      => 50000, // €500.00 (not included in USD total for now)
        ]);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balances");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_usd_equivalent', '1,000.00');
    }

    #[Test]
    public function test_show_requires_authentication(): void
    {
        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balances");

        $response->assertStatus(401);
    }

    #[Test]
    public function test_index_returns_all_balances(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        // Create multiple accounts with balances
        $account2 = Account::factory()->create();

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000,
        ]);

        AccountBalance::create([
            'account_uuid' => $account2->uuid,
            'asset_code'   => 'USD',
            'balance'      => 75000,
        ]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'EUR',
            'balance'      => 30000,
        ]);

        $response = $this->getJson('/api/balances');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'account_uuid',
                        'asset_code',
                        'balance',
                        'formatted',
                        'account' => [
                            'uuid',
                            'user_uuid',
                        ],
                    ],
                ],
                'meta' => [
                    'total_accounts',
                    'total_balances',
                    'asset_totals',
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function test_index_filters_by_asset(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000,
        ]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'EUR',
            'balance'      => 30000,
        ]);

        $response = $this->getJson('/api/balances?asset=USD');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.asset_code', 'USD');
    }

    #[Test]
    public function test_index_filters_by_minimum_balance(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000,
        ]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'EUR',
            'balance'      => 10000,
        ]);

        $response = $this->getJson('/api/balances?min_balance=25000');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.balance', 50000);
    }

    #[Test]
    public function test_index_filters_by_user_uuid(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->create(['user_uuid' => $otherUser->uuid]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000,
        ]);

        AccountBalance::create([
            'account_uuid' => $otherAccount->uuid,
            'asset_code'   => 'USD',
            'balance'      => 75000,
        ]);

        $response = $this->getJson("/api/balances?user_uuid={$this->user->uuid}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.account.user_uuid', (string) $this->user->uuid);
    }

    #[Test]
    public function test_index_respects_limit_parameter(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        // Create 5 balances
        for ($i = 1; $i <= 5; $i++) {
            $account = Account::factory()->create();
            AccountBalance::create([
                'account_uuid' => $account->uuid,
                'asset_code'   => 'USD',
                'balance'      => $i * 10000,
            ]);
        }

        $response = $this->getJson('/api/balances?limit=3');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function test_index_orders_by_balance_descending(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();
        $account3 = Account::factory()->create();

        AccountBalance::create([
            'account_uuid' => $account1->uuid,
            'asset_code'   => 'USD',
            'balance'      => 25000,
        ]);

        AccountBalance::create([
            'account_uuid' => $account2->uuid,
            'asset_code'   => 'USD',
            'balance'      => 100000,
        ]);

        AccountBalance::create([
            'account_uuid' => $account3->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000,
        ]);

        $response = $this->getJson('/api/balances');

        $response->assertStatus(200);

        $balances = collect($response->json('data'))->pluck('balance');
        $this->assertEquals([100000, 50000, 25000], $balances->toArray());
    }

    #[Test]
    public function test_index_calculates_asset_totals(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();

        AccountBalance::create([
            'account_uuid' => $account1->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000, // $500.00
        ]);

        AccountBalance::create([
            'account_uuid' => $account2->uuid,
            'asset_code'   => 'USD',
            'balance'      => 75000, // $750.00
        ]);

        AccountBalance::create([
            'account_uuid' => $account1->uuid,
            'asset_code'   => 'EUR',
            'balance'      => 30000, // €300.00
        ]);

        $response = $this->getJson('/api/balances');

        $response->assertStatus(200);

        $assetTotals = $response->json('meta.asset_totals');
        $this->assertEquals('1250.00', $assetTotals['USD']); // Total: $1250.00
        $this->assertEquals('300.00', $assetTotals['EUR']); // Total: €300.00
    }

    #[Test]
    public function test_index_validates_input_parameters(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        // Test invalid asset code (too long)
        $response = $this->getJson('/api/balances?asset=INVALID');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['asset']);

        // Test invalid min_balance (negative)
        $response = $this->getJson('/api/balances?min_balance=-100');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_balance']);

        // Test invalid user_uuid
        $response = $this->getJson('/api/balances?user_uuid=invalid-uuid');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_uuid']);

        // Test invalid limit (too high)
        $response = $this->getJson('/api/balances?limit=200');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    #[Test]
    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/balances');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_show_handles_account_with_no_balances(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $emptyAccount = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
        ]);

        $response = $this->getJson("/api/accounts/{$emptyAccount->uuid}/balances");

        $response->assertStatus(200)
            ->assertJsonPath('data.account_uuid', (string) $emptyAccount->uuid)
            ->assertJsonCount(0, 'data.balances')
            ->assertJsonPath('data.summary.total_assets', 0)
            ->assertJsonPath('data.summary.total_usd_equivalent', '0.00');
    }

    #[Test]
    public function test_index_includes_metadata_counts(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        // Create 3 accounts
        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();
        $account3 = Account::factory()->create();

        // Create 5 balances
        AccountBalance::create(['account_uuid' => $account1->uuid, 'asset_code' => 'USD', 'balance' => 10000]);
        AccountBalance::create(['account_uuid' => $account1->uuid, 'asset_code' => 'EUR', 'balance' => 20000]);
        AccountBalance::create(['account_uuid' => $account2->uuid, 'asset_code' => 'USD', 'balance' => 30000]);
        AccountBalance::create(['account_uuid' => $account3->uuid, 'asset_code' => 'BTC', 'balance' => 40000]);
        AccountBalance::create(['account_uuid' => $account3->uuid, 'asset_code' => 'EUR', 'balance' => 50000]);

        $response = $this->getJson('/api/balances');

        $response->assertStatus(200);

        // Just verify that the counts are present and positive
        $meta = $response->json('meta');
        $this->assertGreaterThanOrEqual(4, $meta['total_accounts']);
        $this->assertGreaterThanOrEqual(5, $meta['total_balances']);
    }
}
