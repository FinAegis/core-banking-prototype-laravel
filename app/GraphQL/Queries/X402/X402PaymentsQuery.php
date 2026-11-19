<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\X402;

use App\Domain\X402\Models\X402Payment;
use App\Support\MachinePaymentScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class X402PaymentsQuery
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     * @return LengthAwarePaginator<int, X402Payment>
     */
    public function __invoke($_, array $args): LengthAwarePaginator
    {
        // The caller's team's payments (every payment for administrators).
        $query = MachinePaymentScope::teamRecords(X402Payment::query(), Auth::user());

        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }

        if (isset($args['network'])) {
            $query->where('network', $args['network']);
        }

        if (isset($args['payer_address'])) {
            $query->where('payer_address', $args['payer_address']);
        }

        return $query->orderByDesc('created_at')
            ->paginate($args['first'] ?? 20, ['*'], 'page', $args['page'] ?? 1);
    }
}
