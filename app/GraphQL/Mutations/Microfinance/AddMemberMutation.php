<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Microfinance;

use App\Domain\Microfinance\Models\GroupMember;
use App\Domain\Microfinance\Services\GroupLendingService;
use App\Domain\Microfinance\Support\MicrofinanceAccess;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class AddMemberMutation
{
    public function __construct(
        private readonly GroupLendingService $groupLendingService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): GroupMember
    {
        $groupId = (string) $args['group_id'];
        $role = (string) ($args['role'] ?? 'member');

        // An active leader of the group or an operator; only operators
        // assign a role other than member.
        if (! MicrofinanceAccess::mayManageGroup(Auth::user(), $groupId)) {
            throw new AuthorizationException('You are not authorized to manage this group.');
        }

        if (! MicrofinanceAccess::mayAssignRole(Auth::user(), $role)) {
            throw new AuthorizationException('You are not authorized to assign this role.');
        }

        return $this->groupLendingService->addMember(
            groupId: $groupId,
            userId: (int) $args['user_id'],
            role: $role,
        );
    }
}
