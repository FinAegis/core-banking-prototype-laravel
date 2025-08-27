<?php

namespace App\Domain\Exchange\Activities;

use DB;
use Illuminate\Support\Facades\Log;
use Workflow\Activity;

class LogMatchingErrorActivity extends Activity
{
    public function execute(
        object $match,
        string $error
    ): void {
        Log::error(
            'Order matching error',
            [
            'buy_order_id'    => $match->buyOrderId ?? null,
            'sell_order_id'   => $match->sellOrderId ?? null,
            'trade_id'        => $match->tradeId ?? null,
            'executed_amount' => $match->executedAmount ?? null,
            'executed_price'  => $match->executedPrice ?? null,
            'error'           => $error,
            'timestamp'       => now()->toIso8601String(),
            ]
        );

        // Also store in database for audit trail
        DB::table('exchange_matching_errors')->insert(
            [
            'buy_order_id'    => $match->buyOrderId ?? null,
            'sell_order_id'   => $match->sellOrderId ?? null,
            'trade_id'        => $match->tradeId ?? null,
            'executed_amount' => $match->executedAmount ?? null,
            'executed_price'  => $match->executedPrice ?? null,
            'error_message'   => $error,
            'match_data'      => json_encode($match),
            'created_at'      => now(),
            'updated_at'      => now(),
            ]
        );
    }
}
