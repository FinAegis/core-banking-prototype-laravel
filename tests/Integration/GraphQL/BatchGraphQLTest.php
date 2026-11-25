<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Batch\Models\BatchJob;
use App\Models\User;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('GraphQL Batch API', function () {
    it('returns unauthorized without authentication', function () {
        $response = $this->postJson('/graphql', [
            'query' => '{ batchJob(id: 1) { id status } }',
        ]);

        $response->assertOk();
        expect($response->json())->toHaveKey('errors');
    });

    it('queries batch job by id with authentication', function () {
        $user = User::factory()->create();
        $batchJob = BatchJob::create([
            'uuid'            => Str::uuid()->toString(),
            'user_uuid'       => $user->uuid,
            'name'            => 'Bulk Transfer Batch',
            'type'            => 'transfer',
            'status'          => 'pending',
            'total_items'     => 100,
            'processed_items' => 0,
            'failed_items'    => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    query ($id: ID!) {
                        batchJob(id: $id) {
                            id
                            name
                            type
                            status
                            total_items
                            processed_items
                        }
                    }
                ',
                'variables' => ['id' => $batchJob->id],
            ]);

        $response->assertOk();
        $data = $response->json('data.batchJob');
        expect($data['name'])->toBe('Bulk Transfer Batch');
        expect($data['type'])->toBe('transfer');
        expect($data['status'])->toBe('pending');
    });

    it('paginates batch jobs', function () {
        $user = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            BatchJob::create([
                'uuid'            => Str::uuid()->toString(),
                'user_uuid'       => $user->uuid,
                'name'            => "Batch Job {$i}",
                'type'            => 'payment',
                'status'          => 'pending',
                'total_items'     => 50 + $i,
                'processed_items' => 0,
                'failed_items'    => 0,
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    {
                        batchJobs(first: 10, page: 1) {
                            data {
                                id
                                name
                                type
                                status
                                total_items
                            }
                            paginatorInfo {
                                total
                            }
                        }
                    }
                ',
            ]);

        $response->assertOk();
        $data = $response->json('data.batchJobs');
        expect($data['data'])->toBeArray();
        expect($data['paginatorInfo']['total'])->toBeGreaterThanOrEqual(3);
    });

    it('creates a batch job via mutation', function () {
        $user = User::factory()->create();
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        $from = Account::factory()->create(['uuid' => Str::uuid()->toString(), 'user_uuid' => $user->uuid, 'frozen' => false]);
        $to = Account::factory()->create(['uuid' => Str::uuid()->toString(), 'frozen' => false]);
        AccountBalance::query()->updateOrCreate(['account_uuid' => $from->uuid, 'asset_code' => 'USD'], ['balance' => 1_000]);
        $item = ['from_account' => $from->uuid, 'to_account' => $to->uuid, 'amount' => '1.00', 'currency' => 'USD'];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    mutation ($input: CreateBatchJobInput!) {
                        createBatchJob(input: $input) {
                            id
                            name
                            type
                            status
                        }
                    }
                ',
                'variables' => [
                    'input' => [
                        'name'  => 'Payroll Batch',
                        'type'  => 'payment',
                        'items' => [json_encode($item), json_encode($item), json_encode($item)],
                    ],
                ],
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->not->toHaveKey('errors');
        $data = $response->json('data.createBatchJob');
        expect($data['name'])->toBe('Payroll Batch');
        expect($data['type'])->toBe('payment');
    });

    it('refuses batch items that are not transfer objects', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query'     => 'mutation ($input: CreateBatchJobInput!) { createBatchJob(input: $input) { id } }',
                'variables' => ['input' => ['name' => 'Payroll Batch', 'type' => 'payment', 'items' => ['item-1']]],
            ]);

        $response->assertOk();
        expect($response->json('errors.0.extensions.validation'))->toHaveKey('items.0');
        expect(BatchJob::query()->count())->toBe(0);
    });
});
