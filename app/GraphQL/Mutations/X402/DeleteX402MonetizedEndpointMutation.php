<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\X402;

use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class DeleteX402MonetizedEndpointMutation
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): bool
    {
        // Prices apply platform-wide (the payment gate looks routes up by
        // method and path): administrators only.
        if (! StaffRoles::userHasAny(Auth::user(), StaffRoles::ADMINS)) {
            throw new AuthorizationException('You are not authorized to manage monetized endpoints.');
        }

        // Any row, whatever team created it: the price applies to everyone.
        /** @var X402MonetizedEndpoint $endpoint */
        $endpoint = X402MonetizedEndpoint::query()->findOrFail($args['id']);

        Cache::forget("x402:route:{$endpoint->method}:{$endpoint->path}");

        $endpoint->delete();

        return true;
    }
}
