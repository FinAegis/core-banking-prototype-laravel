<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Regulatory;

use App\Domain\Regulatory\Models\RegulatoryReport;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class SubmitReportMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): RegulatoryReport
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Regulatory reports are prepared by compliance staff.
        if (! StaffRoles::userHasAny($user, StaffRoles::COMPLIANCE)) {
            throw new AuthorizationException('You are not authorized to submit regulatory reports.');
        }

        // Create the report record (event sourced via projectors).
        // Filing submission is handled separately via the RegulatoryFilingService
        // once the report has been fully prepared and reviewed.
        /** @var RegulatoryReport $report */
        $report = RegulatoryReport::create([
            'report_type'            => $args['report_type'],
            'jurisdiction'           => $args['jurisdiction'],
            'reporting_period_start' => $args['reporting_period_start'] ?? null,
            'reporting_period_end'   => $args['reporting_period_end'] ?? null,
            'status'                 => RegulatoryReport::STATUS_DRAFT,
            'submitted_by'           => $user->name ?? (string) $user->id,
        ]);

        return $report;
    }
}
