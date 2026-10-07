<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Commerce;

use App\Domain\Commerce\Services\SoulboundTokenService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class RevokeSoulboundTokenMutation
{
    public function __construct(
        private readonly SoulboundTokenService $soulboundTokenService,
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

        // Soulbound tokens are issued and revoked by the platform.
        if (! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to manage soulbound tokens.');
        }

        $this->soulboundTokenService->revokeToken($args['token_hash'], $args['reason']);

        return true;
    }
}
