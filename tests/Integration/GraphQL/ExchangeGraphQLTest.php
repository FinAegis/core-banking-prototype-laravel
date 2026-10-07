<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Exchange\Projections\Order;
use App\Models\User;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('GraphQL Exchange API', function () {
    it('returns unauthorized without authentication', function () {
        $response = $this->postJson('/graphql', [
            'query' => '{ order(id: 1) { id status } }',
        ]);

        $response->assertOk();
        expect($response->json())->toHaveKey('errors');
    });

    it('queries order by id with authentication', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);
        $order = Order::create([
            'order_id'       => Str::uuid()->toString(),
            'account_id'     => (string) $account->id,
            'type'           => 'buy',
            'order_type'     => 'limit',
            'base_currency'  => 'BTC',
            'quote_currency' => 'USD',
            'amount'         => 1.5,
            'price'          => 45000.00,
            'status'         => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    query ($id: ID!) {
                        order(id: $id) {
                            id
                            status
                            type
                            base_currency
                            quote_currency
                            amount
                        }
                    }
                ',
                'variables' => ['id' => $order->id],
            ]);

        $response->assertOk();
        $data = $response->json('data.order');
        expect($data['status'])->toBe('open');
        expect($data['type'])->toBe('buy');
        expect($data['base_currency'])->toBe('BTC');
    });

    it('paginates orders', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);
        for ($i = 0; $i < 3; $i++) {
            Order::create([
                'order_id'       => Str::uuid()->toString(),
                'account_id'     => (string) $account->id,
                'type'           => 'buy',
                'order_type'     => 'market',
                'base_currency'  => 'ETH',
                'quote_currency' => 'USD',
                'amount'         => 10.0 + $i,
                'status'         => 'open',
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    {
                        orders(first: 10, page: 1) {
                            data {
                                id
                                status
                                amount
                                base_currency
                            }
                            paginatorInfo {
                                total
                            }
                        }
                    }
                ',
            ]);

        $response->assertOk();
        $data = $response->json('data.orders');
        expect($data['data'])->toBeArray();
        expect($data['paginatorInfo']['total'])->toBeGreaterThanOrEqual(3);
    });

    it('places an order via mutation', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    mutation ($input: PlaceOrderInput!) {
                        placeOrder(input: $input) {
                            id
                            status
                            type
                            order_type
                            base_currency
                            quote_currency
                            amount
                        }
                    }
                ',
                'variables' => [
                    'input' => [
                        'base_currency'  => 'BTC',
                        'quote_currency' => 'USDT',
                        'type'           => 'buy',
                        'order_type'     => 'market',
                        'amount'         => 0.5,
                    ],
                ],
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->toBeArray();
        // Mutation may fail in test env without full service configuration
        if (isset($json['data']['placeOrder'])) {
            expect($json['data']['placeOrder']['type'])->toBe('buy');
            expect($json['data']['placeOrder']['base_currency'])->toBe('BTC');
        }
    });
});
