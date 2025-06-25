<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Account;
use App\Models\Turnover;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BalanceControllerTest extends TestCase
{
    protected User $user;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->account = Account::factory()->forUser($this->user)->create([
            'balance' => 15000,
        ]);
    }

    public function test_can_get_account_balance()
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
                ]
            ])
            ->assertJson([
                'data' => [
                    'account_uuid' => $this->account->uuid,
                    'balance' => 15000,
                    'frozen' => false,
                ]
            ]);
    }

    public function test_balance_includes_turnover_when_available()
    {
        Sanctum::actingAs($this->user);

        $turnover = Turnover::factory()->create([
            'account_uuid' => $this->account->uuid,
            'debit' => 5000,
            'credit' => 8000,
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
                    ]
                ]
            ])
            ->assertJson([
                'data' => [
                    'turnover' => [
                        'debit' => 5000,
                        'credit' => 8000,
                    ]
                ]
            ]);
    }

    public function test_balance_shows_null_turnover_when_not_available()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'turnover' => null,
                ]
            ]);
    }

    public function test_shows_frozen_status_correctly()
    {
        Sanctum::actingAs($this->user);

        $frozenAccount = Account::factory()->forUser($this->user)->create([
            'frozen' => true,
        ]);

        $response = $this->getJson("/api/accounts/{$frozenAccount->uuid}/balance");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'frozen' => true,
                ]
            ]);
    }

    public function test_returns_404_for_nonexistent_account()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/accounts/00000000-0000-0000-0000-000000000000/balance');

        $response->assertStatus(404);
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance");
        
        $response->assertStatus(401);
    }

    public function test_can_get_balance_summary()
    {
        Sanctum::actingAs($this->user);

        // Create some turnover records for testing
        Turnover::factory()->count(3)->create([
            'account_uuid' => $this->account->uuid,
        ]);

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
                    'monthly_turnovers',
                ]
            ]);
    }

    public function test_balance_summary_calculates_correctly()
    {
        Sanctum::actingAs($this->user);

        // Create specific turnover records for calculation testing
        Turnover::factory()->create([
            'account_uuid' => $this->account->uuid,
            'debit' => 1000,
            'credit' => 2000,
        ]);
        
        Turnover::factory()->create([
            'account_uuid' => $this->account->uuid,
            'debit' => 500,
            'credit' => 1500,
        ]);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance/summary");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'account_uuid' => $this->account->uuid,
                    'statistics' => [
                        'total_credit_12_months' => 3500,
                        'total_debit_12_months' => 1500,
                        'months_analyzed' => 2,
                    ]
                ]
            ]);
    }

    public function test_balance_summary_returns_404_for_nonexistent_account()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/accounts/00000000-0000-0000-0000-000000000000/balance/summary');

        $response->assertStatus(404);
    }

    public function test_balance_summary_requires_authentication()
    {
        $response = $this->getJson("/api/accounts/{$this->account->uuid}/balance/summary");
        
        $response->assertStatus(401);
    }
}