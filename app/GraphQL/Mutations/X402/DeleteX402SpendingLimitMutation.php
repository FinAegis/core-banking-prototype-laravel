<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\X402;

use App\Domain\X402\Models\X402SpendingLimit;
use App\Support\MachinePaymentScope;
use Illuminate\Support\Facades\Auth;

class DeleteX402SpendingLimitMutation
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): bool
    {
        // The caller's team's limit, or the caller's own without a team.
        $limit = MachinePaymentScope::spendingLimits(X402SpendingLimit::query(), Auth::user())
            ->where('agent_id', $args['agent_id'])
            ->firstOrFail();
        $limit->delete();

        return true;
    }
}
