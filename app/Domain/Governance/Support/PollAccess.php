<?php

declare(strict_types=1);

namespace App\Domain\Governance\Support;

use App\Domain\Governance\Models\Poll;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may manage a poll, shared by the REST controller and the GraphQL
 * mutations. Completing a poll can run its execution workflow
 * (GovernanceService::executeWorkflow: asset, feature and configuration
 * changes), so only platform operators (StaffRoles::OPERATORS) attach a
 * workflow to a poll or activate and complete a poll that has one. Other
 * polls are managed by their creator or an operator.
 */
final class PollAccess
{
    public static function isOperator(?Authenticatable $user): bool
    {
        return StaffRoles::userHasAny($user, StaffRoles::OPERATORS);
    }

    /**
     * Activate or complete the poll.
     */
    public static function mayManage(?Authenticatable $user, Poll $poll): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (self::isOperator($user)) {
            return true;
        }

        return ! self::hasExecutionWorkflow($poll) && (string) $poll->created_by === (string) $user->uuid;
    }

    /**
     * Attach an execution workflow to a new poll.
     */
    public static function mayAttachExecutionWorkflow(?Authenticatable $user): bool
    {
        return self::isOperator($user);
    }

    public static function hasExecutionWorkflow(Poll $poll): bool
    {
        return trim((string) $poll->execution_workflow) !== '';
    }
}
