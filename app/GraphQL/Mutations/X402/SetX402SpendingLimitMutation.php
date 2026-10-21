<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\X402;

use App\Domain\X402\Models\X402SpendingLimit;
use App\Support\MachinePaymentScope;
use Illuminate\Support\Facades\Auth;

class SetX402SpendingLimitMutation
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): X402SpendingLimit
    {
        // The caller's team's limits, or the caller's own without a team.
        $owner = MachinePaymentScope::limitOwner(Auth::user());

        $existing = MachinePaymentScope::spendingLimits(X402SpendingLimit::query(), Auth::user())
            ->where('agent_id', $args['agent_id'])
            ->first();

        if ($existing) {
            $existing->update([
                'agent_type'            => $args['agent_type'] ?? $existing->agent_type,
                'daily_limit'           => $args['daily_limit'],
                'per_transaction_limit' => $args['per_transaction_limit'] ?? $existing->per_transaction_limit,
                'auto_pay_enabled'      => $args['auto_pay_enabled'] ?? $existing->auto_pay_enabled,
            ]);

            /** @var X402SpendingLimit */
            return $existing->fresh();
        }

        return X402SpendingLimit::create([
            'agent_id'              => $args['agent_id'],
            'team_id'               => $owner['team_id'],
            'user_id'               => $owner['user_id'],
            'agent_type'            => $args['agent_type'] ?? 'default',
            'daily_limit'           => $args['daily_limit'],
            'per_transaction_limit' => $args['per_transaction_limit'] ?? config('x402.agent_spending.default_per_transaction_limit'),
            'auto_pay_enabled'      => $args['auto_pay_enabled'] ?? false,
            'limit_resets_at'       => now()->addDay(),
        ]);
    }
}
