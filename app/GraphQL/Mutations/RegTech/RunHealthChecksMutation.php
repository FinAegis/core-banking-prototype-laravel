<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\RegTech;

use App\Domain\RegTech\Services\RegTechOrchestrationService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class RunHealthChecksMutation
{
    public function __construct(
        private readonly RegTechOrchestrationService $regTechService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): bool
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Regulator connectivity checks are run by compliance staff.
        if (! StaffRoles::userHasAny($user, StaffRoles::COMPLIANCE)) {
            throw new AuthorizationException('You are not authorized to run regulatory health checks.');
        }

        $this->regTechService->runHealthChecks();

        return true;
    }
}
