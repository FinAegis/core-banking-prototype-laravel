<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Traits\WidgetRespectsModuleVisibility;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class SystemMetricsWidget extends BaseWidget
{
    use WidgetRespectsModuleVisibility;

    protected static ?string $adminModule = 'System';

    protected static ?string $pollingInterval = '10s';

    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $data = Cache::remember('system_metrics_widget', 10, function () {
            return $this->computeStats();
        });

        return $this->buildStats($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function computeStats(): array
    {
        $httpTotal = (int) Cache::get('metrics:http:requests:total', 0);
        $httpErrors = (int) Cache::get('metrics:http:requests:errors', 0);
        $cacheHits = (int) Cache::get('metrics:cache:hits', 0);
        $cacheMisses = (int) Cache::get('metrics:cache:misses', 0);
        $queueProcessed = (int) Cache::get('metrics:queue:processed', 0);
        $queueFailed = (int) Cache::get('metrics:queue:failed', 0);
        $eventsTotal = (int) Cache::get('metrics:events:total', 0);

        return [
            'http_total'      => $httpTotal,
            'http_errors'     => $httpErrors,
            'cache_hits'      => $cacheHits,
            'cache_misses'    => $cacheMisses,
            'queue_processed' => $queueProcessed,
            'queue_failed'    => $queueFailed,
            'events_total'    => $eventsTotal,
        ];
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<Stat>
     */
    private function buildStats(array $data): array
    {
        $httpTotal = (int) ($data['http_total'] ?? 0);
        $httpErrors = (int) ($data['http_errors'] ?? 0);
        $cacheHits = (int) ($data['cache_hits'] ?? 0);
        $cacheMisses = (int) ($data['cache_misses'] ?? 0);
        $eventsTotal = (int) ($data['events_total'] ?? 0);

        $cacheTotal = $cacheHits + $cacheMisses;
        $cacheHitRate = $cacheTotal > 0 ? round(($cacheHits / $cacheTotal) * 100) : 0;
        $errorRate = $httpTotal > 0 ? round(($httpErrors / $httpTotal) * 100, 1) : 0;

        return [
            Stat::make('HTTP Requests', number_format($httpTotal))
                ->description($httpErrors > 0 ? "{$errorRate}% error rate" : 'No errors')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color($errorRate > 5 ? 'danger' : ($errorRate > 1 ? 'warning' : 'success')),

            Stat::make('Cache Hit Rate', "{$cacheHitRate}%")
                ->description("{$cacheHits} hits / {$cacheMisses} misses")
                ->descriptionIcon('heroicon-m-bolt')
                ->color($cacheHitRate >= 80 ? 'success' : ($cacheHitRate >= 50 ? 'warning' : 'danger')),

            Stat::make('Business Events', number_format($eventsTotal))
                ->description('Domain events recorded')
                ->descriptionIcon('heroicon-m-signal')
                ->color('primary'),
        ];
    }
}
