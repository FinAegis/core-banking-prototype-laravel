<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Monitoring\Services\EventStoreHealthCheck;
use App\Filament\Admin\Traits\WidgetRespectsModuleVisibility;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class DomainHealthWidget extends BaseWidget
{
    use WidgetRespectsModuleVisibility;

    protected static ?string $adminModule = 'System';

    protected static ?string $pollingInterval = '60s';

    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $data = Cache::remember('domain_health_widget', 60, function () {
            return $this->computeStats();
        });

        return $this->buildStats($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function computeStats(): array
    {
        $healthCheck = app(EventStoreHealthCheck::class);
        $result = $healthCheck->checkAll();

        $checks = $result['checks'] ?? [];

        // Count healthy domains from event table connectivity
        $tableCheck = $checks['event_table_connectivity'] ?? [];
        $tables = $tableCheck['tables'] ?? [];
        $totalDomains = count($tables);
        $healthyDomains = collect($tables)->filter(fn ($t) => $t['healthy'] ?? false)->count();

        // Get max projector lag
        $lagCheck = $checks['projector_lag'] ?? [];
        $recentEvents = (int) ($lagCheck['recent_events'] ?? 0);

        // Get oldest snapshot age
        $snapshotCheck = $checks['snapshot_freshness'] ?? [];
        $domains = $snapshotCheck['domains'] ?? [];
        $maxAge = collect($domains)
            ->pluck('age_hours')
            ->filter()
            ->max() ?? 0;

        // Get event growth rate
        $growthCheck = $checks['event_growth_rate'] ?? [];
        $eventsPerHour = (int) ($growthCheck['events_per_hour'] ?? 0);

        return [
            'total_domains'    => $totalDomains,
            'healthy_domains'  => $healthyDomains,
            'recent_events'    => $recentEvents,
            'max_snapshot_age' => $maxAge,
            'events_per_hour'  => $eventsPerHour,
        ];
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<Stat>
     */
    private function buildStats(array $data): array
    {
        $total = (int) ($data['total_domains'] ?? 0);
        $healthy = (int) ($data['healthy_domains'] ?? 0);
        $maxAge = (int) ($data['max_snapshot_age'] ?? 0);
        $eventsPerHour = (int) ($data['events_per_hour'] ?? 0);

        $allHealthy = $total > 0 && $healthy === $total;

        return [
            Stat::make('Domains Healthy', "{$healthy}/{$total}")
                ->description($allHealthy ? 'All domains operational' : 'Some domains unhealthy')
                ->descriptionIcon($allHealthy ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($allHealthy ? 'success' : 'warning'),

            Stat::make('Recent Events', number_format((int) ($data['recent_events'] ?? 0)))
                ->description('Events in last 5 minutes')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make('Oldest Snapshot', $maxAge > 0 ? "{$maxAge}h" : 'N/A')
                ->description($maxAge > 168 ? 'Stale — consider refresh' : 'Within threshold')
                ->descriptionIcon('heroicon-m-camera')
                ->color($maxAge > 168 ? 'danger' : 'success'),

            Stat::make('Events/Hour', number_format($eventsPerHour))
                ->description($eventsPerHour > 10000 ? 'High growth rate' : 'Normal growth rate')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($eventsPerHour > 10000 ? 'danger' : 'success'),
        ];
    }
}
