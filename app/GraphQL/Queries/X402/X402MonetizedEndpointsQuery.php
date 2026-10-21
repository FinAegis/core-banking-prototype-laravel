<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\X402;

use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Support\MachinePaymentScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class X402MonetizedEndpointsQuery
{
    /**
     * Resolve a single endpoint by ID or paginated list.
     *
     * @param  null  $_
     * @param  array<string, mixed>  $args
     * @return X402MonetizedEndpoint|LengthAwarePaginator<int, X402MonetizedEndpoint>|null
     */
    public function __invoke($_, array $args): X402MonetizedEndpoint|LengthAwarePaginator|null
    {
        // Prices apply platform-wide: administrators see every row, other
        // users their current team's rows (none without a team).
        $query = MachinePaymentScope::teamRecords(X402MonetizedEndpoint::query(), Auth::user());

        // Single endpoint lookup by ID
        if (isset($args['id'])) {
            /** @var X402MonetizedEndpoint|null */
            return $query->find($args['id']);
        }

        // Paginated list
        if (isset($args['is_active'])) {
            $query->where('is_active', $args['is_active']);
        }

        if (isset($args['network'])) {
            $query->where('network', $args['network']);
        }

        return $query->orderBy('path')
            ->paginate($args['first'] ?? 20, ['*'], 'page', $args['page'] ?? 1);
    }
}
