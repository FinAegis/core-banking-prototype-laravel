<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\X402;

use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class CreateX402MonetizedEndpointMutation
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

        $endpoint = X402MonetizedEndpoint::create([
            'method'      => $args['method'],
            'path'        => $args['path'],
            'price'       => $args['price'],
            'network'     => $args['network'] ?? config('x402.server.default_network'),
            'description' => $args['description'] ?? null,
            'is_active'   => $args['is_active'] ?? true,
            'team_id'     => auth()->user()?->currentTeam?->id,
        ]);

        Cache::forget("x402:route:{$endpoint->method}:{$endpoint->path}");

        return $endpoint;
    }
}
