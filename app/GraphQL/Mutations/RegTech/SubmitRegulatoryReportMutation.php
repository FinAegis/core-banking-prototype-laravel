<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\RegTech;

use App\Domain\RegTech\Services\RegTechOrchestrationService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class SubmitRegulatoryReportMutation
{
    public function __construct(
        private readonly RegTechOrchestrationService $regTechService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): string
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Regulatory filings are made by compliance staff.
        if (! StaffRoles::userHasAny($user, StaffRoles::COMPLIANCE)) {
            throw new AuthorizationException('You are not authorized to submit regulatory reports.');
        }

        $reportData = json_decode($args['report_data'], true) ?: [];

        $result = $this->regTechService->submitReport(
            $args['jurisdiction'],
            $args['report_type'],
            $reportData,
        );

        return json_encode($result) ?: '{}';
    }
}
