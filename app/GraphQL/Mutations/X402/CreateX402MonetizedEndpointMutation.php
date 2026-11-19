<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\X402;

use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Support\StaffRoles;
use GraphQL\Error\Error;
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

        // One active price per method and path (see the REST controller).
        $isActive = (bool) ($args['is_active'] ?? true);
        if ($isActive && X402MonetizedEndpoint::activeRouteExists((string) $args['method'], (string) $args['path'])) {
            throw new Error(X402MonetizedEndpoint::DUPLICATE_ROUTE_MESSAGE);
        }

        $endpoint = new X402MonetizedEndpoint([
            'method'      => $args['method'],
            'path'        => $args['path'],
            'price'       => $args['price'],
            'network'     => $args['network'] ?? config('x402.server.default_network'),
            'description' => $args['description'] ?? null,
            'is_active'   => $isActive,
            'team_id'     => auth()->user()?->currentTeam?->id,
        ]);
        $endpoint->created_by_user_id = auth()->id() === null ? null : (int) auth()->id();
        $endpoint->save();

        Cache::forget("x402:route:{$endpoint->method}:{$endpoint->path}");

        return $endpoint;
    }
}
