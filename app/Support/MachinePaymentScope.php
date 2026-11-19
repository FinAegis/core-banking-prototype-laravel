<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may read or manage machine-payment records (MPP and x402).
 *
 * A caller without a current team is never matched through a null team_id:
 * `where('team_id', null)` becomes `team_id IS NULL`, which matches every
 * row written without a team (all inbound payment records, legacy limits).
 */
final class MachinePaymentScope
{
    /**
     * Team-owned records (payment history, monetized endpoints): every row
     * for platform administrators ({@see StaffRoles::ADMINS}; prices and
     * payment records are platform-wide), the rows of the caller's current
     * team otherwise, and none for a caller without a team.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function teamRecords(Builder $query, ?Authenticatable $user): Builder
    {
        if (StaffRoles::userHasAny($user, StaffRoles::ADMINS)) {
            return $query;
        }

        $teamId = self::teamId($user);
        if ($teamId === null) {
            return $query->whereRaw('1 = 0');
        }

        // Table-qualified columns go to the base query builder (Eloquent's
        // where() forwards a string column to it unchanged).
        $query->getQuery()->where($query->getModel()->getTable() . '.team_id', $teamId);

        return $query;
    }

    /**
     * Agent spending limits of the caller: the rows stored under the
     * caller's current team, or for a caller without a team the rows stored
     * for that user alone. Without a signed-in user (internal callers) only
     * the rows stored with neither a team nor a user match.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function spendingLimits(Builder $query, ?Authenticatable $user): Builder
    {
        $owner = self::limitOwner($user);
        $table = $query->getModel()->getTable();

        if ($owner['team_id'] !== null) {
            $query->getQuery()->where("{$table}.team_id", $owner['team_id']);

            return $query;
        }

        $query->whereNull("{$table}.team_id");

        if ($owner['user_id'] !== null) {
            $query->getQuery()->where("{$table}.user_id", $owner['user_id']);

            return $query;
        }

        return $query->whereNull("{$table}.user_id");
    }

    /**
     * The columns a new spending limit of the caller is stored under (the
     * same namespace {@see self::spendingLimits()} reads).
     *
     * @return array{team_id: int|null, user_id: int|null}
     */
    public static function limitOwner(?Authenticatable $user): array
    {
        $teamId = self::teamId($user);
        if ($teamId !== null) {
            return ['team_id' => $teamId, 'user_id' => null];
        }

        return [
            'team_id' => null,
            'user_id' => $user instanceof User ? (int) $user->getKey() : null,
        ];
    }

    /**
     * The caller's current team id; null without a signed-in user or team.
     */
    public static function teamId(?Authenticatable $user): ?int
    {
        if (! $user instanceof User) {
            return null;
        }

        $teamId = $user->currentTeam?->id;

        return $teamId !== null ? (int) $teamId : null;
    }
}
