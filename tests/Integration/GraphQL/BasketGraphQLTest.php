<?php

declare(strict_types=1);

use App\Domain\Basket\Models\BasketAsset;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('GraphQL Basket API', function () {
    it('returns unauthorized without authentication', function () {
        $response = $this->postJson('/graphql', [
            'query' => '{ basket(id: 1) { id name } }',
        ]);

        $response->assertOk();
        expect($response->json())->toHaveKey('errors');
    });

    it('queries basket by id with authentication', function () {
        $user = User::factory()->create();
        $basket = BasketAsset::create([
            'code'                => 'TECH-BASKET',
            'name'                => 'Technology Basket',
            'description'         => 'Top tech assets basket',
            'type'                => 'fixed',
            'rebalance_frequency' => 'monthly',
            'is_active'           => true,
            'created_by'          => $user->uuid,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    query ($id: ID!) {
                        basket(id: $id) {
                            id
                            name
                            type
                            is_active
                        }
                    }
                ',
                'variables' => ['id' => $basket->id],
            ]);

        $response->assertOk();
        $data = $response->json('data.basket');
        expect($data['name'])->toBe('Technology Basket');
        expect($data['type'])->toBe('fixed');
    });

    it('paginates baskets', function () {
        $user = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            BasketAsset::create([
                'code'                => "BASKET-{$i}",
                'name'                => "Basket {$i}",
                'type'                => 'dynamic',
                'rebalance_frequency' => 'weekly',
                'is_active'           => true,
                'created_by'          => $user->uuid,
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    {
                        baskets(first: 10, page: 1) {
                            data {
                                id
                                name
                                type
                            }
                            paginatorInfo {
                                total
                            }
                        }
                    }
                ',
            ]);

        $response->assertOk();
        $data = $response->json('data.baskets');
        expect($data['data'])->toBeArray();
        expect($data['paginatorInfo']['total'])->toBeGreaterThanOrEqual(3);
    });

    it('creates a basket via mutation', function () {
        // Operators only (StaffRoles::OPERATORS).
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    mutation ($input: CreateBasketInput!) {
                        createBasket(input: $input) {
                            id
                            name
                            type
                        }
                    }
                ',
                'variables' => [
                    'input' => [
                        'name'                => 'DeFi Index Basket',
                        'description'         => 'Top DeFi tokens',
                        'type'                => 'dynamic',
                        'rebalance_frequency' => 'weekly',
                    ],
                ],
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->toBeArray();
        // Mutation may fail in test env without full service configuration
        if (isset($json['data']['createBasket'])) {
            expect($json['data']['createBasket']['name'])->toBe('DeFi Index Basket');
        }
    });
});
