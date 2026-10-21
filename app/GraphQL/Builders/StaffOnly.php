<?php

declare(strict_types=1);

namespace App\GraphQL\Builders;

use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Field builders for GraphQL @find / @paginate queries on internal records
 * that are not shown to the users they concern (compliance alerts and
 * cases, fraud cases, SMS send records). Used as
 * `@builder(method: "App\\GraphQL\\Builders\\StaffOnly@compliance")`.
 * Callers without one of the roles get no rows (null for @find, an empty
 * page for @paginate). Staff see the records of every team: the BelongsToTeam
 * global scope (team_id = the caller's current team) is removed for them.
 */
final class StaffOnly
{
    /**
     * Compliance and fraud staff (StaffRoles::COMPLIANCE).
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function compliance(Builder $builder): Builder
    {
        return $this->restrict($builder, StaffRoles::COMPLIANCE);
    }

    /**
     * Platform operators (StaffRoles::OPERATORS).
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function operators(Builder $builder): Builder
    {
        return $this->restrict($builder, StaffRoles::OPERATORS);
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  list<string>  $roles
     * @return Builder<Model>
     */
    private function restrict(Builder $builder, array $roles): Builder
    {
        $user = Auth::user();

        if ($user instanceof User && $user->hasRole($roles)) {
            return $builder->withoutGlobalScope('team');
        }

        return $builder->whereRaw('1 = 0');
    }
}
