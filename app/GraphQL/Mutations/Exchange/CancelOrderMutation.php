<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Exchange;

use App\Domain\Account\Models\Account;
use App\Domain\Exchange\Projections\Order;
use App\Domain\Exchange\Services\ExchangeService;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class CancelOrderMutation
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
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Only orders placed on the caller's own account can be cancelled
        // (same rule as the REST cancel endpoint); anything else is "not found".
        $account = $user->account;

        /** @var Order|null $order */
        $order = $account instanceof Account
            ? Order::query()
                ->where('order_id', $args['order_id'])
                ->where('account_id', (string) $account->id)
                ->first()
            : null;

        if (! $order) {
            throw new ModelNotFoundException('Order not found.');
        }

        $reason = $args['reason'] ?? 'Cancelled via GraphQL';

        $this->exchangeService->cancelOrder($args['order_id'], $reason);

        return $order->fresh() ?? $order;
    }
}
