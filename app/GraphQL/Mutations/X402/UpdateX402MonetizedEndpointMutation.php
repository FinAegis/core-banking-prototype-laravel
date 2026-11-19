<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\X402;

use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Support\StaffRoles;
use GraphQL\Error\Error;
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

        // Any row, whatever team created it: the price applies to everyone.
        /** @var X402MonetizedEndpoint $endpoint */
        $endpoint = X402MonetizedEndpoint::query()->findOrFail($args['id']);

        $oldCacheKey = "x402:route:{$endpoint->method}:{$endpoint->path}";

        $updateData = [];
        foreach (['price', 'network', 'description', 'is_active', 'asset', 'scheme', 'mime_type'] as $field) {
            if (array_key_exists($field, $args)) {
                $updateData[$field] = $args[$field];
            }
        }

        // Activating a price is refused while another row prices the route.
        $activates = array_key_exists('is_active', $updateData) && (bool) $updateData['is_active'] && ! $endpoint->is_active;
        if ($activates && X402MonetizedEndpoint::activeRouteExists($endpoint->method, $endpoint->path, $endpoint->id)) {
            throw new Error(X402MonetizedEndpoint::DUPLICATE_ROUTE_MESSAGE);
        }

        $endpoint->fill($updateData);
        // An administrator's update vouches for a row written before prices
        // became admin-only.
        $endpoint->created_by_user_id ??= Auth::id() === null ? null : (int) Auth::id();
        $endpoint->save();

        Cache::forget($oldCacheKey);
        Cache::forget("x402:route:{$endpoint->method}:{$endpoint->path}");

        /** @var X402MonetizedEndpoint */
        return $endpoint->fresh();
    }
}
