<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Interledger;

use App\Domain\Interledger\Services\OpenPaymentsService;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class IlpPaymentQuery
{
    public function __construct(
        private readonly OpenPaymentsService $openPaymentsService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{payment_id: string, status: string, checked_at: string}
     */
    public function __invoke(mixed $rootValue, array $args): array
    {
        // Same rule as the Interledger payment mutations and REST
        // GET /api/v1/interledger/payments/{id}: operators only.
        if (! StaffRoles::userHasAny(Auth::user(), StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to use Interledger payments.');
        }

        return $this->openPaymentsService->getPaymentStatus((string) $args['id']);
    }
}
