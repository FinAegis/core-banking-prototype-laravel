<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Models\User;
use Illuminate\Support\Str;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('GraphQL Account API', function () {
    it('returns unauthorized without authentication', function () {
        $response = $this->postJson('/graphql', [
            'query' => '{ account(id: 1) { id name } }',
        ]);

        $response->assertOk();
        $data = $response->json();
        expect($data)->toHaveKey('errors');
    });

    it('queries account by id with authentication', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'name'      => 'Test Account',
            'user_uuid' => $user->uuid,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    query ($id: ID!) {
                        account(id: $id) {
                            id
                            name
                            frozen
                        }
                    }
                ',
                'variables' => ['id' => $account->id],
            ]);

        $response->assertOk();
        $data = $response->json('data.account');
        expect($data['name'])->toBe('Test Account');
    });

    it('queries account by uuid', function () {
        $user = User::factory()->create();
        $uuid = Str::uuid()->toString();
        Account::factory()->create([
            'uuid'      => $uuid,
            'name'      => 'UUID Account',
            'user_uuid' => $user->uuid,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    query ($uuid: String!) {
                        accountByUuid(uuid: $uuid) {
                            uuid
                            name
                        }
                    }
                ',
                'variables' => ['uuid' => $uuid],
            ]);

        $response->assertOk();
        $data = $response->json('data.accountByUuid');
        expect($data['name'])->toBe('UUID Account');
    });

    it('paginates accounts', function () {
        $user = User::factory()->create();
        Account::factory()->count(5)->create(['user_uuid' => $user->uuid]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    {
                        accounts(first: 3, page: 1) {
                            data {
                                id
                                name
                            }
                            paginatorInfo {
                                total
                                currentPage
                                hasMorePages
                            }
                        }
                    }
                ',
            ]);

        $response->assertOk();
        $paginator = $response->json('data.accounts');
        expect($paginator['data'])->toHaveCount(3);
        expect($paginator['paginatorInfo']['total'])->toBeGreaterThanOrEqual(5);
        expect($paginator['paginatorInfo']['hasMorePages'])->toBeTrue();
    });

    it('creates an account via mutation with authenticated user uuid', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    mutation ($input: CreateAccountInput!) {
                        createAccount(input: $input) {
                            id
                            name
                            frozen
                            user_uuid
                        }
                    }
                ',
                'variables' => [
                    'input' => [
                        'name' => 'GraphQL Account',
                    ],
                ],
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->not->toHaveKey('errors');
        $data = $response->json('data.createAccount');
        expect($data)->not->toBeNull();
        expect($data['name'])->toBe('GraphQL Account');
        expect($data['frozen'])->toBeFalse();
        expect($data['user_uuid'])->toBe($user->uuid);
    });
});
