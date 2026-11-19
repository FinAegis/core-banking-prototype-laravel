<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\X402;

use App\Domain\X402\Models\X402Payment;
use App\Support\MachinePaymentScope;
use Illuminate\Support\Facades\Auth;

class X402PaymentStatsQuery
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function __invoke($_, array $args): array
    {
        $period = $args['period'] ?? 'day';
        $period = in_array($period, ['day', 'week', 'month'], true) ? $period : 'day';

        $since = match ($period) {
            'week'  => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subDay(),
        };

        // Over the caller's team's payments (every payment for administrators).
        /** @var object{total_payments: int, total_settled: int, total_failed: int, total_volume_atomic: string, unique_payers: int} $stats */
        $stats = MachinePaymentScope::teamRecords(X402Payment::query(), Auth::user())
            ->toBase()
            ->where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) as total_payments')
            ->selectRaw("SUM(CASE WHEN status = 'settled' THEN 1 ELSE 0 END) as total_settled")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as total_failed")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'settled' THEN CAST(amount AS DECIMAL(20,0)) ELSE 0 END), 0) as total_volume_atomic")
            ->selectRaw('COUNT(DISTINCT payer_address) as unique_payers')
            ->first();

        $totalAtomic = (string) ($stats->total_volume_atomic ?? 0);

        return [
            'period'              => $period,
            'total_payments'      => (int) $stats->total_payments,
            'total_settled'       => (int) $stats->total_settled,
            'total_failed'        => (int) $stats->total_failed,
            'total_volume_atomic' => $totalAtomic,
            'total_volume_usd'    => bcdiv($totalAtomic, '1000000', 6),
            'unique_payers'       => (int) $stats->unique_payers,
        ];
    }
}
