<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Ledger;

use App\Domain\Ledger\Services\LedgerService;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class BalanceQuery
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {
    }

    /**
     * Resolve the balance for a specific account.
     *
     * @param  array<string, mixed>  $args
     * @return array{amount: string, currency: string}
     */
    public function __invoke(mixed $rootValue, array $args): array
    {
        // The general ledger is kept by platform operators (same roles as the
        // ledger mutations and the REST ledger endpoints).
        if (! StaffRoles::userHasAny(Auth::user(), StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to view the general ledger.');
        }

        return $this->ledgerService->getBalance((string) $args['account_code']);
    }
}
