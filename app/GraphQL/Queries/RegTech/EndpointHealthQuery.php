<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\RegTech;

use App\Domain\RegTech\Models\RegulatoryEndpoint;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

final class EndpointHealthQuery
{
    /**
     * @param  array<string, mixed>  $args
     * @return Collection<int, RegulatoryEndpoint>
     */
    public function __invoke(mixed $rootValue, array $args): Collection
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Regulator connections are compliance metadata, as for the
        // regulatoryEndpoint(s) queries: others get an empty list.
        if (! StaffRoles::userHasAny($user, StaffRoles::COMPLIANCE)) {
            return new Collection();
        }

        return RegulatoryEndpoint::query()
            ->where('is_active', true)
            ->orderBy('last_health_check', 'desc')
            ->get();
    }
}
