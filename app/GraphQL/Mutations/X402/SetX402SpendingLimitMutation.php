<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\X402;

use App\Domain\X402\Models\X402SpendingLimit;

class SetX402SpendingLimitMutation
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): X402SpendingLimit
    {
        return X402SpendingLimit::updateOrCreate(
            ['agent_id' => $args['agent_id']],
            [
                'agent_type'            => $args['agent_type'] ?? 'default',
                'daily_limit'           => $args['daily_limit'],
                'per_transaction_limit' => $args['per_transaction_limit'] ?? config('x402.agent_spending.default_per_transaction_limit'),
                'auto_pay_enabled'      => $args['auto_pay_enabled'] ?? false,
                'limit_resets_at'       => now()->addDay(),
            ]
        );
    }
}
