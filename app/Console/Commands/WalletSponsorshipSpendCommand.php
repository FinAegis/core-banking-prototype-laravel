<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\SponsorshipCostTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Operator visibility into today's sponsored-send spend.
 *
 * Sponsored sends draw real platform money (Pimlico gas on EVM, sponsor SOL
 * on Solana). This command shows, for the current UTC day: how many sends
 * were prepared, how many confirmed with a recorded fee per network, the
 * accumulated native-unit cost, a best-effort USD estimate, and the state of
 * the optional daily USD budget (WALLET_SPONSORSHIP_DAILY_BUDGET_USD).
 *
 * Per-network costs come from wallet_send_records (sponsored_fee_raw is
 * persisted at confirmation time); the prepare count and USD spend counter
 * come from the same cache keys the prepare-path guardrail uses.
 */
class WalletSponsorshipSpendCommand extends Command
{
    protected $signature = 'wallet:sponsorship-spend {--json : Output machine-readable JSON}';

    protected $description = "Show today's sponsored-send count and accumulated gas cost per network (UTC day)";

    public function handle(SponsorshipCostTracker $tracker): int
    {
        $dayStart = now()->utc()->startOfDay();

        /** @var \Illuminate\Database\Eloquent\Collection<int, WalletSendRecord> $records */
        $records = WalletSendRecord::query()
            ->where('status', WalletSendRecord::STATUS_CONFIRMED)
            ->whereNotNull('sponsored_fee_raw')
            ->where('confirmed_at', '>=', $dayStart)
            ->get(['network', 'sponsored_fee_raw', 'sponsored_fee_asset']);

        /** @var array<string, array{count: int, fee_raw_total: numeric-string, fee_asset: string|null}> $byNetwork */
        $byNetwork = [];

        foreach ($records as $record) {
            $network = strtolower($record->network);
            $feeRaw = (string) $record->sponsored_fee_raw;

            if (! is_numeric($feeRaw)) {
                continue;
            }

            if (! isset($byNetwork[$network])) {
                $byNetwork[$network] = [
                    'count'         => 0,
                    'fee_raw_total' => '0',
                    'fee_asset'     => $record->sponsored_fee_asset,
                ];
            }

            $byNetwork[$network]['count']++;
            $byNetwork[$network]['fee_raw_total'] = bcadd($byNetwork[$network]['fee_raw_total'], $feeRaw, 0);
        }

        ksort($byNetwork);

        $networks = [];

        foreach ($byNetwork as $network => $totals) {
            $usd = $tracker->estimateUsd($network, $totals['fee_raw_total']);

            $networks[] = [
                'network'       => $network,
                'count'         => $totals['count'],
                'fee_raw_total' => $totals['fee_raw_total'],
                'fee_asset'     => $totals['fee_asset'] ?? SponsorshipCostTracker::feeAssetForNetwork($network),
                'usd_estimate'  => $usd !== null ? bcadd($usd, '0', 6) : null,
            ];
        }

        $prepareCountRaw = Cache::get(SponsorshipCostTracker::globalSendCountCacheKey());
        $prepareCount = is_numeric($prepareCountRaw) ? (int) $prepareCountRaw : 0;

        $spentMicro = $tracker->todaysSpendUsdMicro();
        $spentUsd = bcdiv((string) $spentMicro, '1000000', 6);
        $budgetUsd = $tracker->dailyBudgetUsd();

        $summary = [
            'utc_day'               => $dayStart->format('Y-m-d'),
            'prepares_today'        => $prepareCount,
            'confirmed_with_fee'    => array_sum(array_column($networks, 'count')),
            'networks'              => $networks,
            'budget_spent_usd'      => $spentUsd,
            'budget_configured_usd' => $budgetUsd,
            'budget_exhausted'      => $tracker->isDailyBudgetExhausted(),
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info("Sponsored-send spend for {$summary['utc_day']} (UTC)");
        $this->line("Prepared sends today (count guardrail): {$prepareCount}");
        $this->newLine();

        if ($networks === []) {
            $this->line('No confirmed sends with a recorded sponsored fee today.');
        } else {
            $this->table(
                ['Network', 'Confirmed sends', 'Total fee (native)', 'Asset', '~USD'],
                array_map(
                    static fn (array $row): array => [
                        $row['network'],
                        (string) $row['count'],
                        $row['fee_raw_total'],
                        $row['fee_asset'] ?? '—',
                        $row['usd_estimate'] ?? 'n/a (no rate)',
                    ],
                    $networks,
                ),
            );
        }

        $this->newLine();
        $this->line("USD spend counter (budget basis): \${$spentUsd}");

        if ($budgetUsd === null) {
            $this->line('Daily USD budget: not configured (WALLET_SPONSORSHIP_DAILY_BUDGET_USD unset).');
        } else {
            $state = $summary['budget_exhausted'] ? 'EXHAUSTED — prepares return 429' : 'within budget';
            $this->line("Daily USD budget: \${$budgetUsd} ({$state})");
        }

        return self::SUCCESS;
    }
}
