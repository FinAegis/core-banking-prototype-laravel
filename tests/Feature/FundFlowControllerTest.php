<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Account;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

class FundFlowControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name' => 'Test Account',
        ]);
    }

    public function test_user_can_view_fund_flow_visualization()
    {
        $this->actingAs($this->user);

        $response = $this->get('/fund-flow');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FundFlow/Visualization')
            ->has('accounts')
            ->has('flowData')
            ->has('statistics')
            ->has('networkData')
            ->has('chartData')
            ->has('filters')
        );
    }

    public function test_user_can_filter_fund_flow_by_period()
    {
        $this->actingAs($this->user);

        $response = $this->get('/fund-flow?period=30days');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.period', '30days')
        );
    }

    public function test_user_can_filter_fund_flow_by_account()
    {
        $this->actingAs($this->user);

        $response = $this->get('/fund-flow?account=' . $this->account->uuid);

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.account', $this->account->uuid)
        );
    }

    public function test_user_can_filter_fund_flow_by_type()
    {
        $this->actingAs($this->user);

        $response = $this->get('/fund-flow?flow_type=deposit');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.flow_type', 'deposit')
        );
    }

    public function test_user_can_view_account_fund_flow_details()
    {
        $this->actingAs($this->user);

        $response = $this->get('/fund-flow/account/' . $this->account->uuid);

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FundFlow/AccountDetail')
            ->has('account')
            ->has('inflows')
            ->has('outflows')
            ->has('flowBalance')
            ->has('counterparties')
        );
    }

    public function test_user_cannot_view_other_users_account_flow()
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->create([
            'user_uuid' => $otherUser->uuid,
        ]);

        $this->actingAs($this->user);

        $response = $this->get('/fund-flow/account/' . $otherAccount->uuid);

        $response->assertStatus(404);
    }

    public function test_user_can_export_fund_flow_data()
    {
        $this->actingAs($this->user);

        $response = $this->get('/fund-flow/data');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'flows',
            'period',
            'generated_at',
        ]);
    }

    public function test_fund_flow_statistics_calculate_correctly()
    {
        $this->actingAs($this->user);

        // Create some transactions
        Transaction::create([
            'account_uuid' => $this->account->uuid,
            'type' => 'deposit',
            'amount' => 10000, // $100
            'currency' => 'USD',
            'status' => 'completed',
            'created_at' => now()->subDays(2),
        ]);

        Transaction::create([
            'account_uuid' => $this->account->uuid,
            'type' => 'withdrawal',
            'amount' => 5000, // $50
            'currency' => 'USD',
            'status' => 'completed',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->get('/fund-flow');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('statistics.total_inflow', 10000)
            ->where('statistics.total_outflow', 5000)
            ->where('statistics.net_flow', 5000)
        );
    }

    public function test_fund_flow_respects_date_range_filter()
    {
        $this->actingAs($this->user);

        // Create transactions at different times
        Transaction::create([
            'account_uuid' => $this->account->uuid,
            'type' => 'deposit',
            'amount' => 10000,
            'currency' => 'USD',
            'status' => 'completed',
            'created_at' => now()->subDays(10), // Outside 7-day range
        ]);

        Transaction::create([
            'account_uuid' => $this->account->uuid,
            'type' => 'deposit',
            'amount' => 5000,
            'currency' => 'USD',
            'status' => 'completed',
            'created_at' => now()->subDays(3), // Within 7-day range
        ]);

        $response = $this->get('/fund-flow?period=7days');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('statistics.total_inflow', 5000) // Only recent transaction
        );
    }

    public function test_fund_flow_network_data_includes_accounts_and_external_entities()
    {
        $this->actingAs($this->user);

        // Create a second account
        $account2 = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name' => 'Second Account',
        ]);

        $response = $this->get('/fund-flow');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('networkData.nodes', 2) // Both accounts as nodes
            ->has('networkData.edges')
        );
    }

    public function test_fund_flow_chart_data_aggregates_by_day()
    {
        $this->actingAs($this->user);

        // Create multiple transactions on same day
        Transaction::create([
            'account_uuid' => $this->account->uuid,
            'type' => 'deposit',
            'amount' => 5000,
            'currency' => 'USD',
            'status' => 'completed',
            'created_at' => now()->startOfDay(),
        ]);

        Transaction::create([
            'account_uuid' => $this->account->uuid,
            'type' => 'deposit',
            'amount' => 3000,
            'currency' => 'USD',
            'status' => 'completed',
            'created_at' => now()->startOfDay()->addHours(2),
        ]);

        $response = $this->get('/fund-flow?period=24hours');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('chartData', 2) // 2 days (yesterday and today)
            ->where('chartData.1.inflow', 8000) // Both deposits aggregated
        );
    }

    public function test_unauthorized_user_cannot_access_fund_flow()
    {
        $response = $this->get('/fund-flow');

        $response->assertRedirect('/login');
    }
}