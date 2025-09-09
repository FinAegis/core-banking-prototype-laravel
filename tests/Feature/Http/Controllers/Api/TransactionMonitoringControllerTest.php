<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\Compliance\Models\MonitoringRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionMonitoringControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id]);
        Sanctum::actingAs($this->user);
    }

    public function test_can_get_monitored_transactions(): void
    {
        // Mock the monitoring service to return test data
        $response = $this->getJson('/api/transaction-monitoring');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'total'],
            ]);
    }

    public function test_can_filter_transactions_by_status(): void
    {
        $response = $this->getJson('/api/transaction-monitoring?status=flagged');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    }

    public function test_can_get_transaction_details(): void
    {
        $transaction = Transaction::factory()->create([
            'aggregate_uuid'   => $this->account->uuid,
            'event_properties' => ['amount' => 1000],
            'meta_data'        => [
                'type'              => 'deposit',
                'compliance_status' => 'pending',
                'account_id'        => $this->account->id,
            ],
        ]);
        $response = $this->getJson('/api/transaction-monitoring/transactions/' . $transaction->id);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'transaction',
                    'monitoring',
                ],
            ]);
    }

    public function test_can_flag_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'aggregate_uuid'   => $this->account->uuid,
            'event_properties' => ['amount' => 50000],
            'meta_data'        => [
                'type'              => 'transfer',
                'compliance_status' => 'pending',
                'account_id'        => $this->account->id,
            ],
        ]);
        $response = $this->postJson('/api/transaction-monitoring/' . $transaction->id . '/flag', [
            'reason'   => 'Large transfer to unknown account',
            'severity' => 'high',
            'notes'    => 'Suspicious amount detected',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Transaction flagged successfully');
    }

    public function test_can_clear_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'aggregate_uuid'   => $this->account->uuid,
            'event_properties' => ['amount' => 25000],
            'meta_data'        => [
                'type'              => 'transfer',
                'compliance_status' => 'flagged',
                'account_id'        => $this->account->id,
            ],
        ]);

        // First flag the transaction to create the aggregate with proper status
        $flagResponse = $this->postJson('/api/transaction-monitoring/transactions/' . $transaction->id . '/flag', [
            'reason'   => 'High value transaction',
            'severity' => 'high',
            'notes'    => 'Suspicious large transfer',
        ]);

        $flagResponse->assertOk(); // Ensure the flag operation succeeded

        // Now clear it
        $response = $this->postJson('/api/transaction-monitoring/transactions/' . $transaction->id . '/clear', [
            'reviewer' => (string) $this->user->id,
            'notes'    => 'Regular business transaction - false positive',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Transaction cleared successfully');
    }

    public function test_can_create_monitoring_rule(): void
    {
        $response = $this->postJson('/api/transaction-monitoring/rules', [
            'name'        => 'Large Transfer Detection',
            'type'        => 'amount_threshold',
            'conditions'  => ['amount' => ['>', 10000]],
            'threshold'   => 10000,
            'severity'    => 'high',
            'description' => 'Detect transfers over $10,000',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Rule created successfully')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'type',
                    'conditions',
                    'threshold',
                    'severity',
                ],
            ]);

        $this->assertDatabaseHas('monitoring_rules', [
            'name'     => 'Large Transfer Detection',
            'type'     => 'amount_threshold',
            'severity' => 'high',
        ]);
    }

    public function test_can_update_monitoring_rule(): void
    {
        $rule = MonitoringRule::factory()->create([
            'name'        => 'Test Rule',
            'type'        => 'amount_threshold',
            'conditions'  => ['amount' => ['>', 5000]],
            'threshold'   => 5000,
            'severity'    => 'medium',
            'description' => 'Test rule',
        ]);

        $response = $this->putJson("/api/transaction-monitoring/rules/{$rule->id}", [
            'name'        => 'Updated Rule',
            'threshold'   => 7500,
            'severity'    => 'high',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Rule updated successfully');

        $this->assertDatabaseHas('monitoring_rules', [
            'id'        => $rule->id,
            'name'      => 'Updated Rule',
            'threshold' => 7500,
            'severity'  => 'high',
        ]);
    }

    public function test_can_delete_monitoring_rule(): void
    {
        $rule = MonitoringRule::factory()->create([
            'name'        => 'Test Rule',
            'type'        => 'amount_threshold',
            'conditions'  => ['amount' => ['>', 5000]],
            'threshold'   => 5000,
            'severity'    => 'medium',
            'description' => 'Test rule',
        ]);

        $response = $this->deleteJson("/api/transaction-monitoring/rules/{$rule->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Rule deleted successfully');

        $this->assertSoftDeleted('monitoring_rules', [
            'id' => $rule->id,
        ]);
    }

    public function test_can_analyze_transaction_realtime(): void
    {
        // Create a transaction first
        $transaction = Transaction::factory()->create();

        $response = $this->postJson('/api/transaction-monitoring/analyze/' . $transaction->id, [
            'transaction_id' => (string) $transaction->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'analysis_id',
                    'status',
                    'transaction_id',
                    'risk_score',
                    'patterns',
                    'alerts_generated',
                    'recommendation',
                    'details',
                ],
            ]);
    }

    public function test_can_analyze_transactions_batch(): void
    {
        // Create transactions first
        $transactions = Transaction::factory()->count(3)->create();

        $response = $this->postJson('/api/transaction-monitoring/analyze/batch', [
            'transaction_ids' => $transactions->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'batch_id',
                    'status',
                    'total_transactions',
                    'result',
                ],
            ]);
    }

    public function test_can_get_patterns(): void
    {
        $response = $this->getJson('/api/transaction-monitoring/patterns');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'patterns',
                    'available_types',
                    'statistics' => [
                        'total_detected',
                        'last_updated',
                    ],
                ],
            ]);
    }

    public function test_can_get_thresholds(): void
    {
        $response = $this->getJson('/api/transaction-monitoring/thresholds');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'single_transaction' => ['amount', 'currency'],
                    'daily_aggregate'    => ['amount', 'currency'],
                    'monthly_aggregate'  => ['amount', 'currency'],
                    'velocity'           => ['count_per_hour', 'count_per_day'],
                    'structuring'        => ['threshold', 'count', 'time_window'],
                ],
            ]);
    }

    public function test_can_update_thresholds(): void
    {
        $response = $this->putJson('/api/transaction-monitoring/thresholds', [
            'amount_thresholds' => [
                'high_value'   => 10000,
                'medium_value' => 5000,
                'low_value'    => 1000,
            ],
            'frequency_thresholds' => [
                'daily_limit'  => 10,
                'hourly_limit' => 5,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Thresholds updated successfully');
    }
}
