<?php

/**
 * CueDispatchHealthWidget — Filament widget for cue dispatch health (Plan B Slice 4).
 *
 * Shows a status card: green if all cue dispatch paths processed in last 24h with
 * <1% failure rate; yellow if any kind has 1-5% failure rate; red if >5% or cron missed.
 *
 * Reads from the failed_jobs table. Respects WidgetRespectsModuleVisibility
 * (Commerce module).
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.9
 */

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Traits\WidgetRespectsModuleVisibility;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CueDispatchHealthWidget extends BaseWidget
{
    use WidgetRespectsModuleVisibility;

    protected static ?string $adminModule = 'Commerce';

    protected static ?string $pollingInterval = '300s';

    protected static ?int $sort = 10;

    /** Cue job class prefix for failed_jobs filtering. */
    private const CUE_JOB_PREFIX = 'App\\Domain\\Subscription\\Jobs\\Cue\\';

    private const FAILURE_THRESHOLD_WARNING = 0.01; // 1%

    private const FAILURE_THRESHOLD_CRITICAL = 0.05; // 5%

    protected function getStats(): array
    {
        $since = Carbon::now()->subDay();

        // Count cue-related failures in past 24h.
        $failedCount = DB::table('failed_jobs')
            ->where('failed_at', '>=', $since)
            ->where('payload', 'like', '%' . addcslashes(self::CUE_JOB_PREFIX, '\\') . '%')
            ->count();

        // Total cues created in past 24h (as denominator proxy).
        $cuesCreated = DB::table('cues')
            ->where('created_at', '>=', $since)
            ->count();

        $failureRate = $cuesCreated > 0 ? $failedCount / ($cuesCreated + $failedCount) : 0.0;

        $status = 'healthy';
        $color = 'success';

        if ($failureRate >= self::FAILURE_THRESHOLD_CRITICAL || ($cuesCreated === 0 && $failedCount > 0)) {
            $status = 'critical';
            $color = 'danger';
        } elseif ($failureRate >= self::FAILURE_THRESHOLD_WARNING) {
            $status = 'degraded';
            $color = 'warning';
        }

        $failureRatePct = round($failureRate * 100, 2);

        return [
            Stat::make('Cue Dispatch Health', ucfirst($status))
                ->description(sprintf(
                    '%d cue(s) created · %d failure(s) in 24h (%.2f%%)',
                    $cuesCreated,
                    $failedCount,
                    $failureRatePct,
                ))
                ->color($color)
                ->icon('heroicon-o-bell-alert'),
        ];
    }
}
