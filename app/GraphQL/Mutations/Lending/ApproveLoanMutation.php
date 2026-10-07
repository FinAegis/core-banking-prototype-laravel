<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Lending;

use App\Domain\Lending\Models\LoanApplication;
use App\Domain\Lending\Services\LoanApplicationService;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class ApproveLoanMutation
{
    public function __construct(
        private readonly LoanApplicationService $loanApplicationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): LoanApplication
    {
        if (! config('ledger.direct_funding_enabled')) {
            throw new Error('This operation is not available.');
        }

        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        /** @var LoanApplication|null $application */
        $application = LoanApplication::query()->find($args['id']);

        if (! $application) {
            throw new ModelNotFoundException('Loan application not found.');
        }

        $this->loanApplicationService->processApplication(
            applicationId: (string) $application->id,
            borrowerId: (string) $application->borrower_id,
            requestedAmount: (string) ($args['approved_amount'] ?? $application->requested_amount),
            termMonths: (int) $application->term_months,
            purpose: (string) $application->purpose,
            borrowerInfo: [],
        );

        return $application->fresh() ?? $application;
    }
}
