<?php

declare(strict_types=1);

use App\Domain\Banking\Models\BankAccountModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('GraphQL Banking API', function () {
    it('returns unauthorized for aggregatedBalance without authentication', function () {
        $response = $this->postJson('/graphql', [
            'query' => '{ aggregatedBalance(user_uuid: "test-uuid") { currency total_balance account_count } }',
        ]);

        $response->assertOk();
        expect($response->json())->toHaveKey('errors');
    });

    it('returns aggregated balance for authenticated user', function () {
        $user = User::factory()->create();

        // Create a bank account so groupBy returns at least one result
        BankAccountModel::create([
            'id'             => (string) Illuminate\Support\Str::uuid(),
            'user_uuid'      => $user->uuid,
            'bank_code'      => 'TEST_BANK',
            'external_id'    => 'ext-001',
            'account_number' => encrypt('1234567890'),
            'iban'           => encrypt('DE89370400440532013000'),
            'swift'          => 'TESTBIC',
            'currency'       => 'EUR',
            'account_type'   => 'checking',
            'status'         => 'active',
            'metadata'       => json_encode(['holder_name' => 'Test User']),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    query ($userUuid: String!) {
                        aggregatedBalance(user_uuid: $userUuid) {
                            currency
                            total_balance
                            account_count
                        }
                    }
                ',
                'variables' => ['userUuid' => $user->uuid],
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->toHaveKey('data.aggregatedBalance');
        expect($json['data']['aggregatedBalance'])->toBeArray();
        expect($json['data']['aggregatedBalance'])->toHaveCount(1);
        expect($json['data']['aggregatedBalance'][0]['currency'])->toBe('EUR');
        expect($json['data']['aggregatedBalance'][0]['account_count'])->toBe(1);
    });

    it('returns unauthorized for bankTransfers without authentication', function () {
        $response = $this->postJson('/graphql', [
            'query' => '{ bankTransfers(first: 10) { id status } }',
        ]);

        $response->assertOk();
        expect($response->json())->toHaveKey('errors');
    });

    it('returns empty transfers list for user with no transfers', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    {
                        bankTransfers(first: 10) {
                            id
                            from_account_id
                            to_account_id
                            amount
                            currency
                            status
                            reference
                            created_at
                        }
                    }
                ',
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->toHaveKey('data.bankTransfers');
        expect($json['data']['bankTransfers'])->toBeArray();
        expect($json['data']['bankTransfers'])->toHaveCount(0);
    });

    it('returns transfers for user with existing transfers', function () {
        $user = User::factory()->create();

        $transferId = Illuminate\Support\Str::uuid()->toString();

        DB::table('bank_transfers')->insert([
            'id'              => $transferId,
            'user_uuid'       => $user->uuid,
            'from_bank_code'  => 'TEST_BANK',
            'from_account_id' => 'acct-001',
            'to_bank_code'    => 'TEST_BANK',
            'to_account_id'   => 'acct-002',
            'amount'          => 100.50,
            'currency'        => 'EUR',
            'type'            => 'SEPA_INSTANT',
            'status'          => 'initiated',
            'metadata'        => json_encode(['reference' => 'REF123']),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    {
                        bankTransfers(first: 10) {
                            id
                            from_account_id
                            to_account_id
                            amount
                            currency
                            status
                            reference
                            created_at
                        }
                    }
                ',
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->not->toHaveKey('errors');
        expect($json['data']['bankTransfers'])->toHaveCount(1);
        expect($json['data']['bankTransfers'][0]['id'])->toBe($transferId);
        expect($json['data']['bankTransfers'][0]['from_account_id'])->toBe('acct-001');
        expect($json['data']['bankTransfers'][0]['to_account_id'])->toBe('acct-002');
        expect((float) $json['data']['bankTransfers'][0]['amount'])->toBe(100.50);
        expect($json['data']['bankTransfers'][0]['currency'])->toBe('EUR');
        expect($json['data']['bankTransfers'][0]['status'])->toBe('initiated');
        expect($json['data']['bankTransfers'][0]['reference'])->toBe('REF123');
    });

    it('returns unauthorized for cancelTransfer without authentication', function () {
        $response = $this->postJson('/graphql', [
            'query' => 'mutation { cancelTransfer(transfer_id: "bt_test") { transfer_id status message } }',
        ]);

        $response->assertOk();
        expect($response->json())->toHaveKey('errors');
    });

    it('returns not_found when cancelling non-existent transfer', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    mutation ($transferId: String!) {
                        cancelTransfer(transfer_id: $transferId) {
                            transfer_id
                            status
                            message
                        }
                    }
                ',
                'variables' => ['transferId' => 'bt_non-existent'],
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->not->toHaveKey('errors');
        expect($json['data']['cancelTransfer']['status'])->toBe('not_found');
        expect($json['data']['cancelTransfer']['transfer_id'])->toBe('bt_non-existent');
    });

    it('cancels an initiated transfer successfully', function () {
        $user = User::factory()->create();

        $transferId = Illuminate\Support\Str::uuid()->toString();

        DB::table('bank_transfers')->insert([
            'id'              => $transferId,
            'user_uuid'       => $user->uuid,
            'from_bank_code'  => 'TEST_BANK',
            'from_account_id' => 'acct-001',
            'to_bank_code'    => 'TEST_BANK',
            'to_account_id'   => 'acct-002',
            'amount'          => 50.00,
            'currency'        => 'EUR',
            'type'            => 'SEPA',
            'status'          => 'initiated',
            'metadata'        => json_encode([
                'reference'      => 'CANCEL_TEST',
                'status_history' => [
                    ['status' => 'initiated', 'at' => now()->toIso8601String(), 'note' => 'Transfer initiated'],
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    mutation ($transferId: String!) {
                        cancelTransfer(transfer_id: $transferId) {
                            transfer_id
                            status
                            message
                        }
                    }
                ',
                'variables' => ['transferId' => $transferId],
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->not->toHaveKey('errors');
        expect($json['data']['cancelTransfer']['transfer_id'])->toBe($transferId);
        expect($json['data']['cancelTransfer']['status'])->toBe('cancelled');
        expect($json['data']['cancelTransfer']['message'])->toBe('Transfer cancelled successfully.');

        // Verify DB was updated
        $record = DB::table('bank_transfers')->where('id', $transferId)->first();
        assert($record !== null);
        expect($record->status)->toBe('cancelled');
    });

    it('cannot cancel a completed transfer', function () {
        $user = User::factory()->create();

        $transferId = Illuminate\Support\Str::uuid()->toString();

        DB::table('bank_transfers')->insert([
            'id'              => $transferId,
            'user_uuid'       => $user->uuid,
            'from_bank_code'  => 'TEST_BANK',
            'from_account_id' => 'acct-001',
            'to_bank_code'    => 'TEST_BANK',
            'to_account_id'   => 'acct-002',
            'amount'          => 200.00,
            'currency'        => 'USD',
            'type'            => 'SWIFT',
            'status'          => 'completed',
            'metadata'        => json_encode([
                'reference'      => 'COMPLETED_REF',
                'status_history' => [
                    ['status' => 'initiated', 'at' => now()->subHour()->toIso8601String(), 'note' => 'Transfer initiated'],
                    ['status' => 'completed', 'at' => now()->toIso8601String(), 'note' => 'Transfer completed'],
                ],
            ]),
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    mutation ($transferId: String!) {
                        cancelTransfer(transfer_id: $transferId) {
                            transfer_id
                            status
                            message
                        }
                    }
                ',
                'variables' => ['transferId' => $transferId],
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->not->toHaveKey('errors');
        expect($json['data']['cancelTransfer']['status'])->toBe('completed');
        expect($json['data']['cancelTransfer']['message'])->toBe('Transfer cannot be cancelled in its current state.');
    });

    it('queries available banks with authentication', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '{ availableBanks }',
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->toHaveKey('data.availableBanks');
        expect($json['data']['availableBanks'])->toBeArray();
    });
});
