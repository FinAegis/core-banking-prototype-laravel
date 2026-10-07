<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Exchange;

use App\Domain\Exchange\Projections\Order;
use App\Domain\Exchange\Services\ExchangeService;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class PlaceOrderMutation
{
    public function __construct(
        private readonly ExchangeService $exchangeService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): Order
    {
        if (! config('trading.enabled')) {
            throw new Error('This operation is not available.');
        }

        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $result = $this->exchangeService->placeOrder(
            accountId: (string) $user->id,
            type: $args['type'],
            orderType: $args['order_type'],
            baseCurrency: $args['base_currency'],
            quoteCurrency: $args['quote_currency'],
            amount: $args['amount'],
            price: $args['price'] ?? null,
            stopPrice: $args['stop_price'] ?? null,
        );

        // Return the read-model projection created by the event projector.
        /** @var Order|null $order */
        $order = Order::where('order_id', $result['order_id'])->first();

        if (! $order) {
            // Projector may not have run yet; create a fallback read-model record.
            $order = Order::create([
                'order_id'       => $result['order_id'],
                'account_id'     => (string) $user->id,
                'type'           => $args['type'],
                'order_type'     => $args['order_type'],
                'base_currency'  => $args['base_currency'],
                'quote_currency' => $args['quote_currency'],
                'amount'         => $args['amount'],
                'price'          => $args['price'] ?? null,
                'status'         => 'open',
            ]);
        }

        return $order;
    }
}
