<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketComponent;
use App\Domain\Basket\Models\BasketValue;
use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Models\Vote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class N1QueryOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable query logging
        DB::enableQueryLog();
    }

    protected function tearDown(): void
    {
        // Clear query log
        DB::disableQueryLog();

        parent::tearDown();
    }

    /**
     * Test that BasketController index doesn't have N+1 queries.
     */
    public function test_basket_controller_index_no_n1_queries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create test assets
        $assets = [];
        for ($i = 1; $i <= 5; $i++) {
            $assets[] = Asset::create([
                'code'      => 'TST' . $i,
                'name'      => 'Test Asset ' . $i,
                'type'      => 'crypto',
                'precision' => 8,
                'is_active' => true,
            ]);
        }

        // Create baskets with components and values
        for ($i = 1; $i <= 10; $i++) {
            $basket = BasketAsset::create([
                'code'                => 'BSKT' . $i,
                'name'                => 'Test Basket ' . $i,
                'type'                => 'fixed',
                'rebalance_frequency' => 'never',
                'is_active'           => true,
                'created_by'          => $user->uuid,
            ]);

            // Create components
            $totalWeight = 0;
            foreach (array_slice($assets, 0, 3) as $index => $asset) {
                $weight = $index === 2 ? (100 - $totalWeight) : rand(20, 40);
                $totalWeight += $weight;

                BasketComponent::create([
                    'basket_asset_id' => $basket->id,
                    'asset_code'      => $asset->code,
                    'weight'          => $weight,
                    'is_active'       => true,
                ]);
            }

            // Create values
            for ($j = 1; $j <= 5; $j++) {
                BasketValue::create([
                    'basket_asset_code' => $basket->code,
                    'value'             => rand(1000, 5000) / 100,
                    'calculated_at'     => now()->subDays($j),
                ]);
            }
        }

        // Clear query log before making the request
        DB::flushQueryLog();

        // Make the request
        $response = $this->getJson('/api/v2/baskets');

        $response->assertSuccessful();

        // Analyze queries
        $queries = collect(DB::getQueryLog());

        // Count SELECT queries
        $selectQueries = $queries->filter(function ($query) {
            return str_starts_with($query['query'], 'select');
        });

        // We should have:
        // 1. Query to get baskets
        // 2. Query to get components with assets (eager loaded)
        // 3. Query to get latest values (eager loaded)
        // Total: 3 queries regardless of basket count
        $this->assertLessThanOrEqual(
            4,
            $selectQueries->count(),
            'Too many queries detected. Possible N+1 query issue.'
        );
    }

    /**
     * Test that AssetController show doesn't have N+1 queries.
     */
    public function test_asset_controller_show_no_n1_queries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create test asset
        $asset = Asset::firstOrCreate(
            ['code' => 'BTC'],
            [
                'name'      => 'Bitcoin',
                'type'      => 'crypto',
                'precision' => 8,
                'is_active' => true,
            ]
        );

        // Create account and balances
        $account = Account::create([
            'uuid'      => Str::uuid()->toString(),
            'name'      => 'Test Account',
            'user_uuid' => $user->uuid,
            'balance'   => 0,
        ]);

        // Create 10 different accounts with balances
        for ($i = 0; $i < 10; $i++) {
            $otherAccount = Account::create([
                'uuid'      => Str::uuid()->toString(),
                'name'      => 'Other Account ' . $i,
                'user_uuid' => $user->uuid,
                'balance'   => 0,
            ]);

            AccountBalance::firstOrCreate([
                'account_uuid' => $otherAccount->uuid,
                'asset_code'   => $asset->code,
            ], [
                'balance' => rand(1000, 10000),
            ]);
        }

        // Clear query log before making the request
        DB::flushQueryLog();

        // Make the request
        $response = $this->getJson('/api/v1/assets/BTC');

        $response->assertSuccessful();

        // Analyze queries
        $queries = collect(DB::getQueryLog());

        // Count SELECT queries
        $selectQueries = $queries->filter(function ($query) {
            return str_starts_with($query['query'], 'select');
        });

        // We should have:
        // 1. Query to get the asset
        // 2. Single query to get balance statistics (count and sum)
        // 3. Query to count exchange rates
        // Total: 3 queries
        $this->assertLessThanOrEqual(
            4,
            $selectQueries->count(),
            'Too many queries detected. Possible N+1 query issue.'
        );
    }

    /**
     * Test that UserVotingController getActivePolls doesn't have N+1 queries.
     */
    public function test_user_voting_controller_no_n1_queries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create polls
        for ($i = 1; $i <= 10; $i++) {
            $poll = Poll::create([
                'uuid'        => Str::uuid()->toString(),
                'title'       => 'Test Poll ' . $i,
                'description' => 'Test poll description',
                'type'        => 'single_choice',
                'status'      => 'active',
                'options'     => [
                    ['value' => 'option1', 'label' => 'Option 1'],
                    ['value' => 'option2', 'label' => 'Option 2'],
                ],
                'start_date'             => now()->subDay(),
                'end_date'               => now()->addDays(7),
                'created_by'             => $user->uuid,
                'required_participation' => 0,
                'voting_power_strategy'  => \App\Domain\Governance\Strategies\OneUserOneVoteStrategy::class,
                'metadata'               => [],
            ]);

            // Create votes for first 5 polls
            if ($i <= 5) {
                Vote::create([
                    'poll_id'          => $poll->id,
                    'user_uuid'        => $user->uuid,
                    'selected_options' => ['option1'],
                    'voting_power'     => rand(100, 1000),
                    'voted_at'         => now(),
                ]);
            }
        }

        // Clear query log before making the request
        DB::flushQueryLog();

        // Make the request
        $response = $this->getJson('/api/voting/polls');

        $response->assertSuccessful();

        // Analyze queries
        $queries = collect(DB::getQueryLog());

        // Count SELECT queries
        $selectQueries = $queries->filter(function ($query) {
            return str_starts_with($query['query'], 'select');
        });

        // We should have:
        // 1. Query to get polls
        // 2. Query to get user's votes (eager loaded)
        // 3. Query to count votes (aggregate)
        // 4. Query to sum voting power (aggregate)
        // Total: 4 queries regardless of poll count
        $this->assertLessThanOrEqual(
            5,
            $selectQueries->count(),
            'Too many queries detected. Possible N+1 query issue.'
        );
    }

    /**
     * Helper to debug queries when tests fail.
     */
    private function debugQueries(): void
    {
        $queries = DB::getQueryLog();

        dump('Total queries: ' . count($queries));

        foreach ($queries as $index => $query) {
            dump([
                'query'    => $query['query'],
                'bindings' => $query['bindings'],
                'time'     => $query['time'] ?? null,
            ]);
        }
    }
}
