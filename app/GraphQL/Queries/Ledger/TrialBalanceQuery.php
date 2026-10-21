<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Ledger;

use App\Domain\Ledger\Services\LedgerService;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class TrialBalanceQuery
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {
    }

    /**
     * Resolve the full trial balance.
     *
     * @param  array<string, mixed>  $args
     * @return array<int, array{account_code: string, debit: string, credit: string, balance: string}>
     */
    public function __invoke(mixed $rootValue, array $args): array
    {
        // The general ledger is kept by platform operators (same roles as the
        // ledger mutations and the REST ledger endpoints).
        if (! StaffRoles::userHasAny(Auth::user(), StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to view the general ledger.');
        }

        $trialBalance = $this->ledgerService->getTrialBalance();

        $result = [];
        foreach ($trialBalance as $accountCode => $row) {
            $result[] = [
                'account_code' => (string) $accountCode,
                'debit'        => (string) $row['debit'],
                'credit'       => (string) $row['credit'],
                'balance'      => (string) $row['balance'],
            ];
        }

        return $result;
    }
}
