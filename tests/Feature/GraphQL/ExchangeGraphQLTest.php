<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Exchange\Projections\Order;
use App\Domain\Exchange\Projections\OrderBook;
use App\Domain\Exchange\Projections\Trade;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('GraphQL Exchange API', function () {
    it('paginates orders', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);
        Order::create([
            'order_id'       => 'a1b2c3d4-0000-4000-8000-000000000001',
            'account_id'     => (string) $account->id,
            'type'           => 'buy',
            'order_type'     => 'limit',
            'base_currency'  => 'BTC',
            'quote_currency' => 'USD',
            'amount'         => 1.5,
            'price'          => 50000,
            'status'         => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    {
                        orders(first: 10, page: 1) {
                            data {
                                id
                                order_id
                                type
                                base_currency
                                quote_currency
                                status
                            }
                            paginatorInfo {
                                total
                                currentPage
                            }
                        }
                    }
                ',
            ]);

        $response->assertOk();
        $data = $response->json('data.orders');
        expect($data['data'])->toBeArray();
        expect($data['paginatorInfo']['total'])->toBeGreaterThanOrEqual(1);
    });

    it('paginates trades', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);
        Trade::create([
            'trade_id'          => 'a1b2c3d4-0000-4000-8000-000000000101',
            'buy_order_id'      => 'a1b2c3d4-0000-4000-8000-000000000001',
            'sell_order_id'     => 'a1b2c3d4-0000-4000-8000-000000000002',
            'buyer_account_id'  => (string) $account->id,
            'seller_account_id' => 'ACC-002',
            'base_currency'     => 'BTC',
            'quote_currency'    => 'USD',
            'price'             => 50000,
            'amount'            => 1.0,
            'value'             => 50000,
            'maker_fee'         => 0.1,
            'taker_fee'         => 0.2,
            'maker_side'        => 'buy',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    {
                        trades(first: 10, page: 1) {
                            data {
                                id
                                trade_id
                                price
                                amount
                            }
                            paginatorInfo {
                                total
                            }
                        }
                    }
                ',
            ]);

        $response->assertOk();
        $data = $response->json('data.trades');
        expect($data['data'])->toBeArray();
        expect($data['paginatorInfo']['total'])->toBeGreaterThanOrEqual(1);
    });

    it('queries order books', function () {
        $user = User::factory()->create();
        OrderBook::create([
            'order_book_id'  => 'a1b2c3d4-0000-4000-8000-000000000201',
            'base_currency'  => 'BTC',
            'quote_currency' => 'USD',
            'buy_orders'     => [],
            'sell_orders'    => [],
            'best_bid'       => 49900,
            'best_ask'       => 50100,
            'last_price'     => 50000,
            'volume_24h'     => 150.5,
            'high_24h'       => 51000,
            'low_24h'        => 49000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    {
                        orderBooks(first: 10, page: 1) {
                            data {
                                id
                                order_book_id
                                base_currency
                                quote_currency
                                best_bid
                                best_ask
                            }
                            paginatorInfo {
                                total
                            }
                        }
                    }
                ',
            ]);

        $response->assertOk();
        $data = $response->json('data.orderBooks');
        expect($data['data'])->toBeArray();
        expect($data['paginatorInfo']['total'])->toBeGreaterThanOrEqual(1);
    });

    it('rejects unauthenticated exchange queries', function () {
        $response = $this->postJson('/graphql', [
            'query' => '{ orders(first: 10, page: 1) { data { id } paginatorInfo { total } } }',
        ]);

        $response->assertOk();
        expect($response->json())->toHaveKey('errors');
    });
});
