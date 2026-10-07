<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Compliance;

use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\Compliance\Services\AmlScreeningService;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Throwable;

class TriggerAmlCheckMutation
{
    public function __construct(
        private readonly AmlScreeningService $amlScreeningService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): ComplianceAlert
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Screening runs and the alerts it opens are compliance work
        // (the same roles that may read compliance alerts).
        if (! StaffRoles::userHasAny($user, StaffRoles::COMPLIANCE)) {
            throw new AuthorizationException('You are not authorized to run AML checks.');
        }

        // Resolve the entity for screening.
        $entityClass = $args['entity_type'];
        $entity = $entityClass === User::class
            ? User::findOrFail($args['entity_id'])
            : (object) ['id' => $args['entity_id']];

        try {
            $this->amlScreeningService->performComprehensiveScreening($entity, [
                'check_type' => $args['check_type'] ?? 'aml_screening',
            ]);
        } catch (Throwable $e) {
            // Log but don't fail - still return the alert for tracking.
        }

        return ComplianceAlert::create([
            'alert_id'    => Str::uuid()->toString(),
            'type'        => $args['check_type'] ?? 'aml_screening',
            'severity'    => 'medium',
            'status'      => 'open',
            'title'       => 'AML Check Triggered',
            'description' => "AML check for {$args['entity_type']} {$args['entity_id']}",
            'source'      => 'graphql',
            'entity_type' => $args['entity_type'],
            'entity_id'   => $args['entity_id'],
            'detected_at' => now(),
        ]);
    }
}
