<?php

/**
 * SubscriptionRevenueStatsWidget — recurring-revenue overview for operators.
 *
 * Three stats:
 *   - Active subscribers: iap_subscriptions rows in an "alive" status
 *     (Pro entitlement retained), with a per-tier breakdown.
 *   - Trialing: rows currently in trial.
 *   - Revenue this month: revenue_events emitted since the start of the
 *     current month, summed per denomination with bcmath (refunds are
 *     negative rows, so the sum is net). Money is NEVER floated.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Subscription\Models\IapSubscription;
use App\Domain\Subscription\Models\RevenueEvent;
use App\Filament\Admin\Traits\WidgetRespectsModuleVisibility;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionRevenueStatsWidget extends BaseWidget
{
    use WidgetRespectsModuleVisibility;

    protected static ?string $adminModule = 'Subscriptions';

    protected static ?string $pollingInterval = '60s';

    protected static ?int $sort = 6;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        return [
            $this->activeSubscribersStat(),
            $this->trialingStat(),
            $this->monthRevenueStat(),
        ];
    }

    private function activeSubscribersStat(): Stat
    {
        $byTier = array_map(
            'intval',
            IapSubscription::query()
                ->whereIn('status', IapSubscription::ALIVE_STATUSES)
                ->toBase()
                ->selectRaw('tier, COUNT(*) as aggregate')
                ->groupBy('tier')
                ->orderBy('tier')
                ->pluck('aggregate', 'tier')
                ->all(),
        );

        $total = array_sum($byTier);

        $breakdown = $byTier === []
            ? 'No live IAP subscriptions'
            : 'By tier: ' . implode(' · ', array_map(
                static fn (string $tier, int $count): string => $tier . ' ' . $count,
                array_keys($byTier),
                array_values($byTier),
            ));

        return Stat::make('Active subscribers', (string) $total)
            ->description($breakdown)
            ->color('success');
    }

    private function trialingStat(): Stat
    {
        $trialing = IapSubscription::query()
            ->where('status', IapSubscription::STATUS_TRIALING)
            ->count();

        return Stat::make('Trialing', (string) $trialing)
            ->description('Trials convert or expire at trial_ends_at')
            ->color('info');
    }

    private function monthRevenueStat(): Stat
    {
        /** @var \Illuminate\Support\Collection<int, RevenueEvent> $events */
        $events = RevenueEvent::query()
            ->where('emitted_at', '>=', now()->startOfMonth())
            ->get(['amount', 'amount_decimals', 'amount_denomination']);

        /** @var array<string, numeric-string> $totals */
        $totals = [];

        foreach ($events as $event) {
            $decimals = max(0, $event->amount_decimals);
            // Normalize to major units at a fixed working scale; bcmath only.
            $major = bcdiv((string) $event->amount, (string) (10 ** $decimals), 8);

            $totals[$event->amount_denomination] = bcadd(
                $totals[$event->amount_denomination] ?? '0',
                $major,
                8,
            );
        }

        ksort($totals);

        $value = $totals === []
            ? '0.00'
            : implode(' · ', array_map(
                static fn (string $denomination, string $total): string => self::trimAmount($total) . ' ' . $denomination,
                array_keys($totals),
                array_values($totals),
            ));

        return Stat::make('Revenue this month', $value)
            ->description(sprintf('Net of refunds, from %d revenue event(s)', $events->count()))
            ->color('success');
    }

    /**
     * Strip trailing zeros from a bcmath string without ever rounding,
     * keeping at least two decimal places ("9.99000000" → "9.99").
     */
    private static function trimAmount(string $value): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        $fraction = rtrim($fraction, '0');

        if (strlen($fraction) < 2) {
            $fraction = str_pad($fraction, 2, '0');
        }

        return $integer . '.' . $fraction;
    }
}
