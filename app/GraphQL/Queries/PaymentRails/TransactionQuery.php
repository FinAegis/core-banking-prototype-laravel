<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\PaymentRails;

use App\Domain\PaymentRails\Services\PaymentRailRouter;
use Illuminate\Support\Facades\Auth;

final class TransactionQuery
{
    public function __construct(
        private readonly PaymentRailRouter $router,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>|null
     */
    public function __invoke(mixed $rootValue, array $args): ?array
    {
        // Owner only: another user's transaction resolves to null like an unknown id.
        return $this->router->getTransactionStatus((string) $args['id'], (int) Auth::id());
    }
}
