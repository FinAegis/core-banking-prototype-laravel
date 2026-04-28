<?php

declare(strict_types=1);

namespace App\Domain\MCP\Policy;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class SpendingLimitService
{
    /**
     * Atomically reserve spend against the per-token daily limit.
     * Rolls the 24h window if expired before checking.
     *
     * @return array{allowed: bool, limit_remaining_minor?: int, window_resets_at?: string, error_code?: string}
     */
    public function reserve(string $tokenId, int $amountMinor, string $currency): array
    {
        return DB::transaction(function () use ($tokenId, $amountMinor, $currency): array {
            $row = DB::table('mcp_token_policies')
                ->where('token_id', $tokenId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return ['allowed' => false, 'error_code' => 'NO_POLICY'];
            }

            $windowStart = Carbon::parse((string) $row->daily_window_start_at);
            if ($windowStart->lte(now()->subDay())) {
                DB::table('mcp_token_policies')->where('token_id', $tokenId)->update([
                    'daily_spend_minor'     => 0,
                    'daily_window_start_at' => now(),
                    'updated_at'            => now(),
                ]);
                $row->daily_spend_minor = 0;
                $row->daily_window_start_at = (string) now();
                $windowStart = now();
            }

            if ($currency !== $row->daily_limit_currency) {
                return ['allowed' => false, 'error_code' => 'CURRENCY_MISMATCH'];
            }

            $limit = (int) $row->daily_limit_minor;
            $spent = (int) $row->daily_spend_minor;
            $remaining = $limit - $spent;

            if ($amountMinor > $remaining) {
                return [
                    'allowed'               => false,
                    'limit_remaining_minor' => max(0, $remaining),
                    'window_resets_at'      => $windowStart->copy()->addDay()->toIso8601String(),
                ];
            }

            DB::table('mcp_token_policies')->where('token_id', $tokenId)->update([
                'daily_spend_minor' => DB::raw('daily_spend_minor + ' . (int) $amountMinor),
                'updated_at'        => now(),
            ]);

            return ['allowed' => true];
        });
    }
}
