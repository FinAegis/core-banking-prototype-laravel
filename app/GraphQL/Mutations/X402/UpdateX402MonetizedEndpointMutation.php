<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\X402;

use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class UpdateX402MonetizedEndpointMutation
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): X402MonetizedEndpoint
    {
        // Prices apply platform-wide (the payment gate looks routes up by
        // method and path): administrators only.
        if (! StaffRoles::userHasAny(Auth::user(), StaffRoles::ADMINS)) {
            throw new AuthorizationException('You are not authorized to manage monetized endpoints.');
        }

        $teamId = auth()->user()?->currentTeam?->id;

        /** @var X402MonetizedEndpoint $endpoint */
        $endpoint = X402MonetizedEndpoint::where('team_id', $teamId)->findOrFail($args['id']);

        $oldCacheKey = "x402:route:{$endpoint->method}:{$endpoint->path}";

        $updateData = [];
        foreach (['price', 'network', 'description', 'is_active', 'asset', 'scheme', 'mime_type'] as $field) {
            if (array_key_exists($field, $args)) {
                $updateData[$field] = $args[$field];
            }
        }

        $endpoint->update($updateData);

        Cache::forget($oldCacheKey);
        Cache::forget("x402:route:{$endpoint->method}:{$endpoint->path}");

        /** @var X402MonetizedEndpoint */
        return $endpoint->fresh();
    }
}
