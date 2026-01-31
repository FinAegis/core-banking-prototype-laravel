<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class AssetControllerTest extends ControllerTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any existing assets to prevent conflicts
        Asset::query()->delete();

        $this->user = User::factory()->create();
    }

    #[Test]
    public function test_index_returns_active_assets_by_default(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        // Create assets using firstOrCreate to avoid conflicts
        $usd = Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name'      => 'US Dollar',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
                'metadata'  => ['symbol' => '$'],
            ]
        );

        $eur = Asset::firstOrCreate(
            ['code' => 'EUR'],
            [
                'name'      => 'Euro',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
                'metadata'  => ['symbol' => '€'],
            ]
        );

        $gbp = Asset::firstOrCreate(
            ['code' => 'GBP'],
            [
                'name'      => 'British Pound',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => false, // Inactive
                'metadata'  => ['symbol' => '£'],
            ]
        );

        $response = $this->getJson('/api/v1/assets');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'code',
                        'name',
                        'type',
                        'symbol',
                        'precision',
                        'is_active',
                        'metadata',
                    ],
                ],
                'meta' => [
                    'total',
                    'active',
                    'types' => [
                        'fiat',
                        'crypto',
                        'commodity',
                    ],
                ],
            ])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.active', 2)
            ->assertJsonPath('meta.types.fiat', 3);
    }

    #[Test]
    public function test_index_includes_inactive_assets_when_requested(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'GBP'], ['name' => 'British Pound', 'type' => 'fiat', 'precision' => 2, 'is_active' => false]);

        $response = $this->getJson('/api/v1/assets?include_inactive=true');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function test_index_filters_by_asset_type(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'BTC'], ['name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'GOLD'], ['name' => 'Gold', 'type' => 'commodity', 'precision' => 3, 'is_active' => true]);

        $response = $this->getJson('/api/v1/assets?type=crypto');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BTC');
    }

    #[Test]
    public function test_index_searches_by_code_and_name(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name'      => 'US Dollar',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );

        Asset::firstOrCreate(
            ['code' => 'USDC'],
            [
                'name'      => 'USD Coin',
                'type'      => 'crypto',
                'precision' => 6,
                'is_active' => true,
            ]
        );

        Asset::firstOrCreate(
            ['code' => 'EUR'],
            [
                'name'      => 'Euro',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );

        $response = $this->getJson('/api/v1/assets?search=USD');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'USD')
            ->assertJsonPath('data.1.code', 'USDC');
    }

    #[Test]
    public function test_index_combines_filters(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name'      => 'US Dollar',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );

        Asset::firstOrCreate(
            ['code' => 'USDT'],
            [
                'name'      => 'USD Tether',
                'type'      => 'crypto',
                'precision' => 6,
                'is_active' => true,
            ]
        );

        Asset::firstOrCreate(
            ['code' => 'BTC'],
            [
                'name'      => 'Bitcoin',
                'type'      => 'crypto',
                'precision' => 8,
                'is_active' => true,
            ]
        );

        $response = $this->getJson('/api/v1/assets?type=crypto&search=USD');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'USDT');
    }

    #[Test]
    public function test_index_orders_assets_by_code(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        Asset::firstOrCreate(['code' => 'ZAR'], ['name' => 'South African Rand', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'AUD'], ['name' => 'Australian Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);

        $response = $this->getJson('/api/v1/assets');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('AUD', $data[0]['code']);
        $this->assertEquals('USD', $data[1]['code']);
        $this->assertEquals('ZAR', $data[2]['code']);
    }

    #[Test]
    public function test_index_returns_empty_array_when_no_assets(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/v1/assets');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
                'meta' => [
                    'total'  => 0,
                    'active' => 0,
                    'types'  => [
                        'fiat'      => 0,
                        'crypto'    => 0,
                        'commodity' => 0,
                    ],
                ],
            ]);
    }

    #[Test]
    public function test_index_does_not_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/assets');

        $response->assertStatus(200);
    }

    #[Test]
    public function test_show_returns_asset_details(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $asset = Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name'      => 'US Dollar',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
                'metadata'  => [
                    'symbol'    => '$',
                    'category'  => 'currency',
                    'regulated' => true,
                ],
            ]
        );

        $response = $this->getJson('/api/v1/assets/USD');

        $response->assertStatus(200)
            ->assertJsonPath('id', $asset->id)
            ->assertJsonPath('code', 'USD')
            ->assertJsonPath('name', 'US Dollar')
            ->assertJsonPath('type', 'fiat')
            ->assertJsonPath('symbol', '$')
            ->assertJsonPath('precision', 2)
            ->assertJsonPath('is_active', true)
            ->assertJsonPath('metadata.symbol', '$')
            ->assertJsonPath('metadata.category', 'currency')
            ->assertJsonPath('metadata.regulated', true)
            ->assertJsonStructure([
                'id',
                'code',
                'name',
                'type',
                'symbol',
                'precision',
                'is_active',
                'metadata',
                'statistics' => [
                    'total_supply',
                    'circulating_supply',
                    'market_data',
                    'total_accounts',
                    'total_balance',
                    'active_rates',
                ],
                'created_at',
                'updated_at',
            ]);
    }

    #[Test]
    public function test_show_includes_statistics(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $asset = Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name'      => 'US Dollar',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );

        // Create accounts and account balances for the asset
        for ($i = 0; $i < 3; $i++) {
            $account = Account::factory()->create();
            AccountBalance::factory()->create([
                'account_uuid' => $account->uuid,
                'asset_code'   => 'USD',
                'balance'      => 10000, // 100.00 USD each
            ]);
        }

        $response = $this->getJson('/api/v1/assets/USD');

        $response->assertStatus(200)
            ->assertJsonPath('statistics.total_accounts', 3)
            ->assertJsonPath('statistics.total_balance', '300.00');
    }

    #[Test]
    public function test_show_handles_case_insensitive_codes(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name'      => 'US Dollar',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );

        $response = $this->getJson('/api/v1/assets/usd');

        $response->assertStatus(200)
            ->assertJsonPath('code', 'USD');
    }

    #[Test]
    public function test_show_returns_404_for_non_existent_asset(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/v1/assets/INVALID');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Asset not found',
                'error'   => 'The specified asset code was not found',
            ]);
    }

    #[Test]
    public function test_show_excludes_inactive_assets_by_default(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name'      => 'US Dollar',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => false,
            ]
        );

        $response = $this->getJson('/api/v1/assets/USD');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_show_includes_inactive_assets_when_requested(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        // Force create a new inactive asset
        $asset = Asset::create([
            'code'      => 'INACTIVE_TEST',
            'name'      => 'Inactive Test Asset',
            'type'      => 'fiat',
            'precision' => 2,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/assets/INACTIVE_TEST?include_inactive=true');

        $response->assertStatus(200)
            ->assertJsonPath('code', 'INACTIVE_TEST')
            ->assertJsonPath('is_active', false);
    }

    #[Test]
    public function test_show_does_not_require_authentication(): void
    {
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);

        $response = $this->getJson('/api/v1/assets/USD');

        $response->assertStatus(200);
    }

    #[Test]
    public function test_index_handles_multiple_asset_types_correctly(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        // Create fiat assets
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);

        // Create crypto assets
        Asset::firstOrCreate(['code' => 'BTC'], ['name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'ETH'], ['name' => 'Ethereum', 'type' => 'crypto', 'precision' => 18, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'USDT'], ['name' => 'Tether', 'type' => 'crypto', 'precision' => 6, 'is_active' => true]);

        // Create commodity asset
        Asset::firstOrCreate(['code' => 'GOLD'], ['name' => 'Gold', 'type' => 'commodity', 'precision' => 3, 'is_active' => true]);

        $response = $this->getJson('/api/v1/assets');

        $response->assertStatus(200)
            ->assertJsonPath('meta.types.fiat', 2)
            ->assertJsonPath('meta.types.crypto', 3)
            ->assertJsonPath('meta.types.commodity', 1);
    }

    #[Test]
    public function test_show_formats_balance_with_asset_precision(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $asset = Asset::firstOrCreate(
            ['code' => 'BTC'],
            [
                'name'      => 'Bitcoin',
                'type'      => 'crypto',
                'precision' => 8,
                'is_active' => true,
            ]
        );

        $account = Account::factory()->create();
        AccountBalance::factory()->create([
            'account_uuid' => $account->uuid,
            'asset_code'   => 'BTC',
            'balance'      => 12345678, // 0.12345678 BTC
        ]);

        $response = $this->getJson('/api/v1/assets/BTC');

        $response->assertStatus(200)
            ->assertJsonPath('statistics.total_balance', '0.12345678');
    }

    #[Test]
    public function test_index_search_is_case_insensitive(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name'      => 'US Dollar',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );

        $response = $this->getJson('/api/v1/assets?search=dollar');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'USD');
    }
}
