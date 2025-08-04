<?php

namespace Tests\Feature;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\Transaction;
use App\Domain\Asset\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class TransactionStatusTrackingTest extends DomainTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user with team (required for Jetstream)
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
        ]);

        // Create USD asset
        Asset::updateOrCreate(
            ['code' => 'USD'],
            [
                'name'      => 'US Dollar',
                'symbol'    => '$',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );

        // Add balance
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 100000, // $1,000
        ]);
    }

    #[Test]
    public function user_can_view_transaction_status_tracking_page()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('transactions.status'));

        $response->assertStatus(200);
        $response->assertViewIs('transactions.status-tracking');
        $response->assertViewHas('accounts');
        $response->assertViewHas('pendingTransactions');
        $response->assertViewHas('completedTransactions');
        $response->assertViewHas('statistics');
        $response->assertViewHas('filters');
    }

    #[Test]
    public function user_can_filter_transactions_by_status()
    {
        $this->actingAs($this->user);

        // Create test transactions
        $this->createTestTransaction('pending');
        $this->createTestTransaction('completed');
        $this->createTestTransaction('failed');

        $response = $this->get(route('transactions.status', ['status' => 'pending']));

        $response->assertStatus(200);
        $response->assertViewIs('transactions.status-tracking');
        $response->assertViewHas('filters', function ($filters) {
            return $filters['status'] === 'pending';
        });
    }

    #[Test]
    public function user_can_view_transaction_details()
    {
        $this->actingAs($this->user);

        $transaction = $this->createTestTransaction('processing');

        $response = $this->get(route('transactions.status.show', $transaction->id));

        $response->assertStatus(200);
        $response->assertViewIs('transactions.status-detail');
        $response->assertViewHas('transaction');
        $response->assertViewHas('timeline');
        $response->assertViewHas('relatedTransactions');
    }

    #[Test]
    public function user_can_get_real_time_transaction_status()
    {
        $this->actingAs($this->user);

        $transaction = $this->createTestTransaction('processing');

        $response = $this->get(route('transactions.status.status', $transaction->id));

        $response->assertStatus(200);
        $response->assertJson([
            'id'     => $transaction->id,
            'status' => 'processing',
        ]);
        $response->assertJsonStructure([
            'id',
            'status',
            'estimated_completion',
            'last_updated',
            'can_cancel',
            'can_retry',
        ]);
    }

    #[Test]
    public function user_can_cancel_pending_transaction()
    {
        $this->actingAs($this->user);

        // Create a withdrawal transaction (which can be cancelled)
        $transaction = $this->createTestTransaction('pending', 'withdrawal');

        $response = $this->post(route('transactions.status.cancel', $transaction->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Transaction cancelled successfully',
        ]);
    }

    #[Test]
    public function user_cannot_cancel_completed_transaction()
    {
        $this->actingAs($this->user);

        $transaction = $this->createTestTransaction('completed');

        $response = $this->post(route('transactions.status.cancel', $transaction->id));

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Transaction cannot be cancelled',
        ]);
    }

    #[Test]
    public function user_can_retry_failed_transaction()
    {
        $this->actingAs($this->user);

        $transaction = $this->createTestTransaction('failed');

        $response = $this->post(route('transactions.status.retry', $transaction->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Transaction retry initiated',
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'new_transaction_id',
        ]);
    }

    #[Test]
    public function user_cannot_retry_successful_transaction()
    {
        $this->actingAs($this->user);

        $transaction = $this->createTestTransaction('completed');

        $response = $this->post(route('transactions.status.retry', $transaction->id));

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Transaction cannot be retried',
        ]);
    }

    #[Test]
    public function user_can_filter_transactions_by_date_range()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('transactions.status', [
            'date_from' => now()->subDays(7)->format('Y-m-d'),
            'date_to'   => now()->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('transactions.status-tracking');
        $response->assertViewHas('filters', function ($filters) {
            return $filters['date_from'] === now()->subDays(7)->format('Y-m-d')
                && $filters['date_to'] === now()->format('Y-m-d');
        });
    }

    #[Test]
    public function statistics_show_correct_transaction_counts()
    {
        $this->actingAs($this->user);

        // Create various transactions
        $this->createTestTransaction('completed');
        $this->createTestTransaction('completed');
        $this->createTestTransaction('pending');
        $this->createTestTransaction('failed');

        $response = $this->get(route('transactions.status'));

        $response->assertStatus(200);
        $response->assertViewIs('transactions.status-tracking');
        $response->assertViewHas('statistics', function ($stats) {
            // Handle both array and object formats
            $stats = (array) $stats;

            return $stats['total'] === 4
                && $stats['completed'] === 2
                && $stats['pending'] === 1
                && $stats['failed'] === 1
                && $stats['success_rate'] === 50.0;
        });
    }

    #[Test]
    public function user_cannot_view_other_users_transactions()
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->create([
            'user_uuid' => $otherUser->uuid,
        ]);

        // Create transaction for other user
        $transactionUuid = \Str::uuid();
        $transactionId = \DB::table('transaction_projections')->insertGetId([
            'uuid'         => $transactionUuid,
            'account_uuid' => $otherAccount->uuid,
            'asset_code'   => 'USD',
            'type'         => 'deposit',
            'amount'       => 10000,
            'status'       => 'pending',
            'reference'    => 'TEST-' . uniqid(),
            'hash'         => hash('sha3-512', $transactionUuid . $otherAccount->uuid . time()),
            'metadata'     => json_encode(['test' => true]),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $transaction = (object) ['id' => $transactionId];

        $this->actingAs($this->user);

        $response = $this->get(route('transactions.status.show', $transaction->id));

        $response->assertStatus(404);
    }

    /**
     * Helper method to create test transactions.
     */
    private function createTestTransaction($status = 'pending', $type = 'deposit')
    {
        $uuid = \Str::uuid();
        $id = \DB::table('transaction_projections')->insertGetId([
            'uuid'         => $uuid,
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'type'         => $type,
            'amount'       => rand(1000, 50000),
            'status'       => $status,
            'reference'    => 'TEST-' . uniqid(),
            'hash'         => hash('sha3-512', $uuid . $this->account->uuid . time()),
            'metadata'     => json_encode([
                'description' => 'Test transaction',
                'source'      => 'test',
            ]),
            'created_at' => now()->subMinutes(rand(1, 60)),
            'updated_at' => $status === 'completed' ? now()->addMinutes(5) : now(),
        ]);

        return (object) ['id' => $id];
    }
}
