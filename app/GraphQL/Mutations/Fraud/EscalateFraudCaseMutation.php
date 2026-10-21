<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Fraud;

use App\Domain\Fraud\Models\FraudCase;
use App\Domain\Fraud\Services\FraudCaseService;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class EscalateFraudCaseMutation
{
    public function __construct(
        private readonly FraudCaseService $fraudCaseService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): FraudCase
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Fraud cases are handled by compliance staff only (same roles as the
        // fraudCase / fraudCases queries).
        if (! $user instanceof User || ! $user->hasRole(StaffRoles::COMPLIANCE)) {
            throw new AuthorizationException('You are not authorized to manage fraud cases.');
        }

        // Compliance staff work cases of every team (the BelongsToTeam scope
        // would otherwise limit them to their own current team).
        /** @var FraudCase|null $fraudCase */
        $fraudCase = FraudCase::query()->allTeams()->find($args['id']);

        if (! $fraudCase) {
            throw new ModelNotFoundException('Fraud case not found.');
        }

        $reason = $args['notes'] ?? $args['reason'] ?? 'Escalated via GraphQL';

        return $this->fraudCaseService->escalateCase($fraudCase, $reason);
    }
}
