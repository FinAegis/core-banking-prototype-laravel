<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\X402;

use App\Domain\X402\Models\X402SpendingLimit;
use App\Support\MachinePaymentScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class X402SpendingLimitsQuery
{
    /**
     * Resolve a single spending limit by agent_id or paginated list.
     *
     * @param  null  $_
     * @param  array<string, mixed>  $args
     * @return X402SpendingLimit|LengthAwarePaginator<int, X402SpendingLimit>|null
     */
    public function __invoke($_, array $args): X402SpendingLimit|LengthAwarePaginator|null
    {
        // The caller's team's limits, or the caller's own without a team.
        $query = MachinePaymentScope::spendingLimits(X402SpendingLimit::query(), Auth::user());

        // Single limit lookup by agent_id
        if (isset($args['agent_id'])) {
            return $query->where('agent_id', $args['agent_id'])->first();
        }

        // Paginated list
        return $query->orderBy('agent_id')
            ->paginate($args['first'] ?? 20, ['*'], 'page', $args['page'] ?? 1);
    }
}
