<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Microfinance;

use App\Domain\Microfinance\Services\LoanProvisioningService;
use App\Domain\Microfinance\Support\MicrofinanceAccess;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class ProvisionSummaryQuery
{
    public function __construct(
        private readonly LoanProvisioningService $loanProvisioningService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{standard: string, substandard: string, doubtful: string, loss: string, total: string}
     */
    public function __invoke(mixed $rootValue, array $args): array
    {
        // Loan loss provisions cover every loan of the institution.
        if (! MicrofinanceAccess::isOperator(Auth::user())) {
            throw new AuthorizationException('You are not authorized to view loan provisions.');
        }

        return $this->loanProvisioningService->getTotalProvisions();
    }
}
