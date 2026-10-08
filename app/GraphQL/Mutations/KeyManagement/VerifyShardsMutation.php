<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\KeyManagement;

use App\Domain\KeyManagement\Models\KeyShardRecord;
use App\Domain\KeyManagement\Services\ShamirService;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class VerifyShardsMutation
{
    public function __construct(
        private readonly ShamirService $shamirService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // The caller's own shards, or any user's for an operator.
        $mayVerify = (string) $args['user_uuid'] === (string) $user->uuid
            || StaffRoles::userHasAny($user, StaffRoles::OPERATORS);

        if (! $mayVerify) {
            throw new AuthorizationException('You are not authorized to verify these shards.');
        }

        $shards = KeyShardRecord::query()
            ->where('user_uuid', $args['user_uuid'])
            ->where('key_version', $args['key_version'])
            ->where('status', 'active')
            ->get()
            ->toArray();

        return $this->shamirService->verifyShards($shards, $args['expected_public_key'] ?? '');
    }
}
