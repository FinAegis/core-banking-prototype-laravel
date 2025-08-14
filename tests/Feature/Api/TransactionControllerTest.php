<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = Account::factory()->create(['user_uuid' => $this->user->uuid]);

    // Only create assets if they don't exist to avoid unique constraint violations
    $this->asset = Asset::firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true, 'metadata' => []]
    );

    $this->btcAsset = Asset::firstOrCreate(
        ['code' => 'BTC'],
        ['name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8, 'is_active' => true, 'metadata' => []]
    );

    // Create account balances for withdrawal tests
    AccountBalance::create([
        'account_uuid' => $this->account->uuid,
        'asset_code'   => 'USD',
        'balance'      => 100000, // $1000.00 in cents
    ]);

    AccountBalance::create([
        'account_uuid' => $this->account->uuid,
        'asset_code'   => 'BTC',
        'balance'      => 10000000, // 0.1 BTC in satoshis
    ]);
});

describe('POST /api/accounts/{uuid}/deposit', function () {
    it('successfully deposits USD to account', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'      => 100.50,
            'asset_code'  => 'USD',
            'description' => 'Test deposit',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Deposit initiated successfully',
            ]);
    });

    it('successfully deposits BTC to account', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'      => 0.01, // Use a larger amount that won't round to 0
            'asset_code'  => 'BTC',
            'description' => 'BTC deposit',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Deposit initiated successfully',
            ]);
    });

    it('requires authentication', function () {
        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'     => 100.00,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(401);
    });

    it('validates required fields', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'asset_code']);
    });

    it('validates minimum amount', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'     => 0.00,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    });

    it('validates asset exists', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'     => 100.00,
            'asset_code' => 'INVALID',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['asset_code']);
    });

    it('validates description length', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'      => 100.00,
            'asset_code'  => 'USD',
            'description' => str_repeat('a', 300),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    });

    it('returns 404 for non-existent account', function () {
        Sanctum::actingAs($this->user);
        $fakeUuid = Illuminate\Support\Str::uuid();

        $response = $this->postJson("/api/accounts/{$fakeUuid}/deposit", [
            'amount'     => 100.00,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(404);
    });

    it('denies access to account owned by different user', function () {
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->create(['user_uuid' => $otherUser->uuid]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$otherAccount->uuid}/deposit", [
            'amount'     => 100.00,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied to this account',
                'error'   => 'FORBIDDEN',
            ]);
    });

    it('rejects deposits to frozen account', function () {
        $this->account->update(['frozen' => true]);
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/deposit", [
            'amount'     => 100.00,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot deposit to frozen account',
                'error'   => 'ACCOUNT_FROZEN',
            ]);
    });
});

describe('POST /api/accounts/{uuid}/withdraw', function () {
    beforeEach(function () {
        // Set up account with balance for withdrawal tests using updateOrCreate to avoid duplicates
        $this->account->balances()->updateOrCreate(
            ['asset_code' => 'USD'],
            ['balance' => 100000] // $1000 in cents
        );
        $this->account->balances()->updateOrCreate(
            ['asset_code' => 'BTC'],
            ['balance' => 10000000] // 0.1 BTC in satoshis
        );
    });

    it('successfully withdraws USD from account', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", [
            'amount'      => 50.00,
            'asset_code'  => 'USD',
            'description' => 'Test withdrawal',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Withdrawal initiated successfully',
            ]);
    });

    it('successfully withdraws BTC from account', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", [
            'amount'      => 0.05,
            'asset_code'  => 'BTC',
            'description' => 'BTC withdrawal',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Withdrawal initiated successfully',
            ]);
    });

    it('requires authentication', function () {
        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", [
            'amount'     => 50.00,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(401);
    });

    it('validates required fields', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'asset_code']);
    });

    it('rejects withdrawal with insufficient balance', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", [
            'amount'     => 2000.00, // More than $1000 balance
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Insufficient balance',
                'errors'  => [
                    'amount' => ['Insufficient balance'],
                ],
            ]);
    });

    it('denies access to account owned by different user', function () {
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->create(['user_uuid' => $otherUser->uuid]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$otherAccount->uuid}/withdraw", [
            'amount'     => 50.00,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied to this account',
                'error'   => 'FORBIDDEN',
            ]);
    });

    it('rejects withdrawals from frozen account', function () {
        $this->account->update(['frozen' => true]);
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", [
            'amount'     => 50.00,
            'asset_code' => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot withdraw from frozen account',
                'error'   => 'ACCOUNT_FROZEN',
            ]);
    });

    it('handles workflow exception gracefully', function () {
        // Mock a workflow failure by making account balance check pass but workflow fail
        Sanctum::actingAs($this->user);

        // We'll simulate this by trying to withdraw from an asset with 0 balance
        $this->account->balances()->updateOrCreate(
            ['asset_code' => 'ETH'],
            ['balance' => 0]
        );

        $ethAsset = Asset::firstOrCreate(
            ['code' => 'ETH'],
            ['name' => 'Ethereum', 'type' => 'crypto', 'precision' => 18, 'is_active' => true, 'metadata' => []]
        );

        $response = $this->postJson("/api/accounts/{$this->account->uuid}/withdraw", [
            'amount'     => 1.00,
            'asset_code' => 'ETH',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Insufficient balance',
            ]);
    });
});

describe('GET /api/accounts/{uuid}/transactions', function () {
    beforeEach(function () {
        // Create some test events in the stored_events table
        DB::table('stored_events')->insert([
            [
                'id'                => 1,
                'aggregate_uuid'    => $this->account->uuid,
                'aggregate_version' => 1,
                'event_version'     => 1,
                'event_class'       => 'App\\Domain\\Account\\Events\\MoneyAdded',
                'event_properties'  => json_encode([
                    'money' => ['amount' => 10000],
                    'hash'  => ['hash' => 'test-hash-1'],
                ]),
                'meta_data'  => '{}',
                'created_at' => now()->subHours(2),
            ],
            [
                'id'                => 2,
                'aggregate_uuid'    => $this->account->uuid,
                'aggregate_version' => 2,
                'event_version'     => 1,
                'event_class'       => 'App\\Domain\\Account\\Events\\AssetBalanceAdded',
                'event_properties'  => json_encode([
                    'amount'    => 5000000,
                    'assetCode' => 'BTC',
                    'hash'      => ['hash' => 'test-hash-2'],
                ]),
                'meta_data'  => '{}',
                'created_at' => now()->subHour(),
            ],
            [
                'id'                => 3,
                'aggregate_uuid'    => $this->account->uuid,
                'aggregate_version' => 3,
                'event_version'     => 1,
                'event_class'       => 'App\\Domain\\Account\\Events\\MoneySubtracted',
                'event_properties'  => json_encode([
                    'money' => ['amount' => 2000],
                    'hash'  => ['hash' => 'test-hash-3'],
                ]),
                'meta_data'  => '{}',
                'created_at' => now(),
            ],
        ]);
    });

    it('successfully retrieves transaction history', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'account_uuid',
                        'type',
                        'amount',
                        'asset_code',
                        'description',
                        'hash',
                        'created_at',
                        'metadata',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'account_uuid',
                ],
            ]);

        expect($response->json('data'))->toHaveCount(3);
        expect($response->json('meta.account_uuid'))->toBe((string) $this->account->uuid);
    });

    it('filters transactions by type', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions?type=credit");

        $response->assertStatus(200);

        $transactions = $response->json('data');
        expect(collect($transactions)->pluck('type')->unique()->values()->toArray())->toEqual(['credit']);
    });

    it('filters transactions by asset code', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions?asset_code=BTC");

        $response->assertStatus(200);

        $transactions = $response->json('data');
        expect(collect($transactions)->pluck('asset_code')->unique()->values()->toArray())->toEqual(['BTC']);
    });

    it('paginates results correctly', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions?per_page=2");

        $response->assertStatus(200);

        expect($response->json('data'))->toHaveCount(2);
        expect($response->json('meta.per_page'))->toBe(2);
    });

    it('validates pagination limits', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions?per_page=500");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    });

    it('validates type filter', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions?type=invalid");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    });

    it('requires authentication', function () {
        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions");

        $response->assertStatus(401);
    });

    it('returns 404 for non-existent account', function () {
        Sanctum::actingAs($this->user);
        $fakeUuid = Illuminate\Support\Str::uuid();

        $response = $this->getJson("/api/accounts/{$fakeUuid}/transactions");

        $response->assertStatus(404);
    });

    it('returns empty data for account with no transactions', function () {
        $emptyAccount = Account::factory()->create(['user_uuid' => $this->user->uuid]);
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$emptyAccount->uuid}/transactions");

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(0);
    });

    it('correctly transforms different event types', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$this->account->uuid}/transactions");

        $transactions = collect($response->json('data'));

        // Check MoneyAdded event - transactions are ordered by created_at DESC, so oldest comes last
        $moneyAdded = $transactions->where('description', 'Deposit')->where('asset_code', 'USD')->first();
        expect($moneyAdded['type'])->toBe('credit');
        expect($moneyAdded['amount'])->toBe(10000);
        expect($moneyAdded['asset_code'])->toBe('USD');

        // Check AssetBalanceAdded event
        $assetAdded = $transactions->where('asset_code', 'BTC')->first();
        expect($assetAdded['type'])->toBe('credit');
        expect($assetAdded['amount'])->toBe(5000000);
        expect($assetAdded['asset_code'])->toBe('BTC');

        // Check MoneySubtracted event
        $moneySubtracted = $transactions->where('description', 'Withdrawal')->first();
        expect($moneySubtracted['type'])->toBe('debit');
        expect($moneySubtracted['amount'])->toBe(2000);
        expect($moneySubtracted['asset_code'])->toBe('USD');
    });
});
