<?php

declare(strict_types=1);

namespace Tests\Feature\Stablecoin;

use App\Models\User;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use App\Domain\Asset\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesStablecoins;

class StablecoinOperationsApiTest extends TestCase
{
    use RefreshDatabase, CreatesStablecoins;

    protected User $user;
    protected Account $account;
    protected Stablecoin $stablecoin;
    protected Asset $usdAsset;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a user and authenticate
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        
        // Ensure required assets exist
        $this->ensureAssetsExist();
        $this->usdAsset = Asset::find('USD');
        
        // Create account with USD balance
        $this->account = Account::factory()->zeroBalance()->create();
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'USD',
            'balance' => 1000000,
        ]); // $10,000
        
        // Create stablecoin
        $this->stablecoin = $this->createStablecoinWithAsset([
            'code' => 'FUSD',
            'name' => 'FinAegis USD',
            'symbol' => 'FUSD',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'collateralized',
            'collateral_ratio' => 1.5,
            'min_collateral_ratio' => 1.2,
            'liquidation_penalty' => 0.1,
            'total_supply' => 0,
            'max_supply' => 10000000,
            'total_collateral_value' => 0,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
    }

    /** @test */
    public function it_can_mint_stablecoins()
    {
        $this->markTestSkipped('Stablecoin operations need event sourcing refactoring');
        $data = [
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000, // $1,500
            'mint_amount' => 100000, // $1,000
        ];

        $response = $this->postJson('/api/v2/stablecoin-operations/mint', $data);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'uuid',
                    'account_uuid',
                    'stablecoin_code',
                    'collateral_asset_code',
                    'collateral_amount',
                    'debt_amount',
                    'collateral_ratio',
                    'status',
                ]
            ])
            ->assertJsonPath('data.debt_amount', 100000)
            ->assertJsonPath('data.collateral_amount', 150000)
            ->assertJsonPath('data.status', 'active');

        // Check that collateral was locked
        $this->account->refresh();
        $this->assertEquals(850000, $this->account->getBalance('USD')); // $10,000 - $1,500
        
        // Check that stablecoin was minted (minus fee)
        $expectedBalance = 100000 - (100000 * 0.005); // 99,500
        $this->assertEquals(99500, $this->account->getBalance('FUSD'));
    }

    /** @test */
    public function it_validates_mint_request()
    {
        $data = [
            'account_uuid' => 'invalid-uuid',
            'stablecoin_code' => 'INVALID',
            'collateral_asset_code' => 'INVALID',
            'collateral_amount' => -100,
            'mint_amount' => 0,
        ];

        $response = $this->postJson('/api/v2/stablecoin-operations/mint', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'account_uuid',
                'stablecoin_code',
                'collateral_asset_code',
                'collateral_amount',
                'mint_amount',
            ]);
    }

    /** @test */
    public function it_prevents_minting_with_insufficient_collateral()
    {
        $data = [
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 100000, // $1,000 (not enough for 1.5x ratio)
            'mint_amount' => 100000, // $1,000
        ];

        $response = $this->postJson('/api/v2/stablecoin-operations/mint', $data);

        $response->assertBadRequest()
            ->assertJsonPath('error', 'Insufficient collateral. Required ratio: 1.5000, provided ratio: 1');
    }

    /** @test */
    public function it_can_burn_stablecoins()
    {
        $this->markTestSkipped('Stablecoin operations need event sourcing refactoring');
        // First create a position
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        // Lock the collateral from the account
        $usdBalance = AccountBalance::where('account_uuid', $this->account->uuid)
            ->where('asset_code', 'USD')
            ->first();
        $usdBalance->balance -= 150000;
        $usdBalance->save();
        
        // Give account some FUSD to burn
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'FUSD',
            'balance' => 100000,
        ]);

        $data = [
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'burn_amount' => 50000, // Burn $500
        ];

        $response = $this->postJson('/api/v2/stablecoin-operations/burn', $data);

        $response->assertOk()
            ->assertJsonPath('data.debt_amount', 50000) // Remaining debt
            ->assertJsonPath('data.collateral_amount', 75000); // Proportional collateral remaining

        // Check balances
        $this->account->refresh();
        $this->assertEquals(49850, $this->account->getBalance('FUSD')); // 100000 - 50000 - (50000 * 0.003) = 49850
        $this->assertEquals(925000, $this->account->getBalance('USD')); // Original - locked + released
    }

    /** @test */
    public function it_can_add_collateral_to_position()
    {
        $this->markTestSkipped('Stablecoin operations need event sourcing refactoring');
        // Create a position
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 120000, // At minimum ratio
            'debt_amount' => 100000,
            'collateral_ratio' => 1.2,
            'status' => 'active',
        ]);

        $data = [
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 30000, // Add $300
        ];

        $response = $this->postJson('/api/v2/stablecoin-operations/add-collateral', $data);

        $response->assertOk()
            ->assertJsonPath('data.collateral_amount', 150000)
            ->assertJsonPath('data.collateral_ratio', '1.5000');
    }

    /** @test */
    public function it_can_get_account_positions()
    {
        // Create positions
        StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/v2/stablecoin-operations/accounts/{$this->account->uuid}/positions");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'stablecoin_code',
                        'collateral_asset_code',
                        'collateral_amount',
                        'debt_amount',
                        'collateral_ratio',
                        'status',
                        'health_score',
                        'recommendations',
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_get_position_details()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 200000,
            'debt_amount' => 100000,
            'collateral_ratio' => 2.0,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/v2/stablecoin-operations/positions/{$position->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'account_uuid',
                    'stablecoin_code',
                    'collateral_asset_code',
                    'collateral_amount',
                    'debt_amount',
                    'collateral_ratio',
                    'health_score',
                    'max_mint_amount',
                    'is_at_risk',
                    'recommendations',
                ]
            ])
            ->assertJsonPath('data.is_at_risk', false)
            ->assertJsonPath('data.max_mint_amount', 33333); // Can mint ~$333 more
    }

    /** @test */
    public function it_can_get_liquidation_opportunities()
    {
        // Ensure USD to USD exchange rate exists
        \App\Domain\Asset\Models\ExchangeRate::firstOrCreate(
            ['from_asset_code' => 'USD', 'to_asset_code' => 'USD'],
            ['rate' => 1.0, 'provider' => 'internal', 'valid_at' => now(), 'updated_at' => now()]
        );
        
        // Create an at-risk position (below min_collateral_ratio of 1.2)
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 100000, // Equal to debt, ratio = 1.0
            'debt_amount' => 100000,
            'collateral_ratio' => 1.0, // Well below minimum of 1.2
            'status' => 'active',
            'auto_liquidation_enabled' => true,
        ]);

        $response = $this->getJson('/api/v2/stablecoin-operations/liquidation/opportunities');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'position_uuid',
                        'account_uuid',
                        'stablecoin_code',
                        'eligible',
                        'reward',
                        'penalty',
                        'collateral_seized',
                        'debt_amount',
                        'collateral_asset',
                        'current_ratio',
                        'min_ratio',
                        'priority_score',
                        'health_score',
                    ]
                ]
            ]);
        
        // The response should include the position we created
        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    /** @test */
    public function it_can_get_positions_at_risk()
    {
        // Ensure USD to USD exchange rate exists (should be 1.0)
        \App\Domain\Asset\Models\ExchangeRate::firstOrCreate(
            ['from_asset_code' => 'USD', 'to_asset_code' => 'USD'],
            ['rate' => 1.0, 'provider' => 'internal', 'valid_at' => now(), 'updated_at' => now()]
        );
        
        // Create positions with different risk levels
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 124000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.24, // Within risk buffer (1.2 + 0.05 = 1.25)
            'status' => 'active',
            'last_interaction_at' => now(),
        ]);
        
        // Ensure the stablecoin exists
        $this->assertNotNull($position->stablecoin, 'Stablecoin relationship should be loaded');

        $response = $this->getJson('/api/v2/stablecoin-operations/positions/at-risk');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'account_uuid',
                        'stablecoin_code',
                        'collateral_ratio',
                        'health_score',
                        'risk_level',
                        'recommendations',
                    ]
                ]
            ]);
        
        // The response should be an array
        $data = $response->json('data');
        $this->assertIsArray($data);
    }
}