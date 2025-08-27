<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\Turnover;
use App\Domain\Account\Services\Cache\AccountCacheService;
use App\Domain\Account\Services\Cache\TurnoverCacheService;
use App\Domain\Asset\Models\Asset;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class BalanceControllerTest extends ControllerTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected AccountCacheService $accountCache;

    protected TurnoverCacheService $turnoverCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance'   => 50000, // 500.00
            'frozen'    => false,
        ]);

        // Create USD asset if it doesn't exist
        Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name'      => 'US Dollar',
                'symbol'    => '$',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );

        // Create AccountBalance for USD
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000, // 500.00
        ]);

        $this->accountCache = $this->app->make(AccountCacheService::class);
        $this->turnoverCache = $this->app->make(TurnoverCacheService::class);
    }

    #[Test]
    public function test_show_returns_account_balance(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'account_uuid',
                    'balance',
                    'frozen',
                    'last_updated',
                    'turnover',
                ],
            ])
            ->assertJson([
                'data' => [
                    'account_uuid' => $this->account->uuid,
                    'balance'      => 50000,
                    'frozen'       => false,
                ],
            ]);
    }

    #[Test]
    public function test_show_returns_turnover_data_when_exists(): void
    {
        Sanctum::actingAs($this->user);

        // Create turnover data
        $turnover = Turnover::create([
            'account_uuid' => $this->account->uuid,
            'date'         => now()->toDateString(),
            'debit'        => 10000,
            'credit'       => 15000,
            'count'        => 5,
            'amount'       => 25000,
            'created_at'   => now()->subDay(),
            'updated_at'   => now(),
        ]);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'turnover' => [
                        'debit',
                        'credit',
                        'period_start',
                        'period_end',
                    ],
                ],
            ])
            ->assertJsonPath('data.turnover.debit', '10000.00')
            ->assertJsonPath('data.turnover.credit', '15000.00');
    }

    #[Test]
    public function test_show_returns_null_turnover_when_none_exists(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance");

        $response->assertStatus(200)
            ->assertJsonPath('data.turnover', null);
    }

    #[Test]
    public function test_show_uses_cached_balance_when_available(): void
    {
        Sanctum::actingAs($this->user);

        // Set a different cached balance
        $this->accountCache->updateBalance($this->account->uuid, 75000);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance");

        $response->assertStatus(200)
            ->assertJsonPath('data.balance', 75000);
    }

    #[Test]
    public function test_show_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/accounts/non-existent-uuid/balance');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_show_handles_frozen_accounts(): void
    {
        Sanctum::actingAs($this->user);

        $frozenAccount = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance'   => 25000,
            'frozen'    => true,
        ]);

        // Create AccountBalance for the frozen account
        AccountBalance::create([
            'account_uuid' => $frozenAccount->uuid,
            'asset_code'   => 'USD',
            'balance'      => 25000,
        ]);

        $response = $this->getJson("/api/accounts/{$frozenAccount->uuid}/balance");

        $response->assertStatus(200)
            ->assertJsonPath('data.frozen', true)
            ->assertJsonPath('data.balance', 25000);
    }

    #[Test]
    public function test_show_requires_authentication(): void
    {
        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance");

        $response->assertStatus(401);
    }

    #[Test]
    public function test_summary_returns_detailed_balance_statistics(): void
    {
        Sanctum::actingAs($this->user);

        // Create some turnover data for the last 3 months
        for ($i = 0; $i < 3; $i++) {
            Turnover::create([
                'account_uuid' => $this->account->uuid,
                'date'         => now()->subMonths($i)->toDateString(),
                'debit'        => 10000 + ($i * 1000), // 10000, 11000, 12000
                'credit'       => 15000 + ($i * 1000), // 15000, 16000, 17000
                'count'        => 10 + $i,
                'amount'       => 25000 + ($i * 2000),
                'created_at'   => now()->subMonths($i),
                'updated_at'   => now()->subMonths($i),
            ]);
        }

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance/summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'account_uuid',
                    'current_balance',
                    'frozen',
                    'statistics' => [
                        'total_debit_12_months',
                        'total_credit_12_months',
                        'average_monthly_debit',
                        'average_monthly_credit',
                        'months_analyzed',
                    ],
                    'monthly_turnovers' => [
                        '*' => ['month', 'debit', 'credit', 'net'],
                    ],
                ],
            ])
            ->assertJsonPath('data.account_uuid', (string) $this->account->uuid)
            ->assertJsonPath('data.current_balance', 50000);
    }

    #[Test]
    public function test_summary_calculates_statistics_correctly(): void
    {
        Sanctum::actingAs($this->user);

        // Create turnover data
        Turnover::create([
            'account_uuid' => $this->account->uuid,
            'date'         => now()->subMonth()->toDateString(),
            'debit'        => 10000,
            'credit'       => 20000,
            'count'        => 5,
            'amount'       => 30000,
            'created_at'   => now()->subMonth(),
        ]);

        Turnover::create([
            'account_uuid' => $this->account->uuid,
            'date'         => now()->toDateString(),
            'debit'        => 15000,
            'credit'       => 25000,
            'count'        => 8,
            'amount'       => 40000,
            'created_at'   => now(),
        ]);

        // Mock the cache service to return specific statistics
        $mockTurnoverCache = Mockery::mock(TurnoverCacheService::class)->makePartial();
        $mockTurnoverCache->shouldReceive('getStatistics')
            ->with($this->account->uuid)
            ->andReturn([
                'total_debit'            => 25000,
                'total_credit'           => 45000,
                'average_monthly_debit'  => 12500,
                'average_monthly_credit' => 22500,
                'months_analyzed'        => 2,
            ]);

        $this->app->instance(TurnoverCacheService::class, $mockTurnoverCache);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.statistics.total_debit_12_months', 25000)
            ->assertJsonPath('data.statistics.total_credit_12_months', 45000)
            ->assertJsonPath('data.statistics.average_monthly_debit', 12500)
            ->assertJsonPath('data.statistics.average_monthly_credit', 22500)
            ->assertJsonPath('data.statistics.months_analyzed', 2);
    }

    #[Test]
    public function test_summary_handles_no_turnover_data(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.statistics.total_debit_12_months', 0)
            ->assertJsonPath('data.statistics.total_credit_12_months', 0)
            ->assertJsonPath('data.statistics.months_analyzed', 0)
            ->assertJsonCount(0, 'data.monthly_turnovers');
    }

    #[Test]
    public function test_summary_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/accounts/non-existent-uuid/balance/summary');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_summary_requires_authentication(): void
    {
        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance/summary");

        $response->assertStatus(401);
    }

    #[Test]
    public function test_summary_formats_monthly_turnover_data(): void
    {
        Sanctum::actingAs($this->user);

        // Create specific turnover data with a known date
        $testDate = Carbon::create(2024, 3, 15);
        $turnover = Turnover::create([
            'account_uuid' => $this->account->uuid,
            'date'         => $testDate->toDateString(),
            'debit'        => 30000,
            'credit'       => 50000,
            'count'        => 10,
            'amount'       => 80000,
            'created_at'   => $testDate,
            'updated_at'   => $testDate,
        ]);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance/summary");

        $response->assertStatus(200);

        $turnovers = $response->json('data.monthly_turnovers');
        $this->assertCount(1, $turnovers);
        // Just check that we have the turnover data with correct values
        $this->assertEquals(30000, $turnovers[0]['debit']);
        $this->assertEquals(50000, $turnovers[0]['credit']);
        $this->assertEquals(20000, $turnovers[0]['net']); // credit - debit
        // Month format check - should be Y-m format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $turnovers[0]['month']);
    }

    #[Test]
    public function test_show_returns_balance_with_zero_balance(): void
    {
        Sanctum::actingAs($this->user);

        $zeroBalanceAccount = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance'   => 0,
            'frozen'    => false,
        ]);

        $response = $this->getJson("/api/accounts/{$zeroBalanceAccount->uuid}/balance");

        $response->assertStatus(200)
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.frozen', false);
    }

    #[Test]
    public function test_summary_handles_large_turnover_volumes(): void
    {
        Sanctum::actingAs($this->user);

        // Create 12 months of turnover data
        for ($i = 0; $i < 12; $i++) {
            Turnover::create([
                'account_uuid' => $this->account->uuid,
                'date'         => now()->subMonths($i)->toDateString(),
                'debit'        => 100000 * ($i + 1), // Increasing amounts
                'credit'       => 150000 * ($i + 1),
                'count'        => 20 + $i,
                'amount'       => 250000 * ($i + 1),
                'created_at'   => now()->subMonths($i),
                'updated_at'   => now()->subMonths($i),
            ]);
        }

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance/summary");

        $response->assertStatus(200)
            ->assertJsonCount(12, 'data.monthly_turnovers')
            ->assertJsonPath('data.statistics.months_analyzed', 12);
    }

    #[Test]
    public function test_show_uses_account_balance_when_cache_unavailable(): void
    {
        Sanctum::actingAs($this->user);

        // Mock cache to return null
        $mockAccountCache = Mockery::mock(AccountCacheService::class)->makePartial();
        $mockAccountCache->shouldReceive('get')
            ->with($this->account->uuid)
            ->andReturn($this->account);
        $mockAccountCache->shouldReceive('getBalance')
            ->with($this->account->uuid)
            ->andReturn(null);

        $this->app->instance(AccountCacheService::class, $mockAccountCache);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance");

        $response->assertStatus(200)
            ->assertJsonPath('data.balance', 50000); // Falls back to account balance
    }
}
