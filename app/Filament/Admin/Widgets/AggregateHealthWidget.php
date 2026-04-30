<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Monitoring\Services\EventStoreService;
use App\Filament\Admin\Traits\WidgetRespectsModuleVisibility;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AggregateHealthWidget extends BaseWidget
{
    use WidgetRespectsModuleVisibility;

    protected static ?string $adminModule = 'System';

    protected static ?string $pollingInterval = '60s';

    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $data = Cache::remember('aggregate_health_widget', 60, function () {
            return $this->computeStats();
        });

        return $this->buildStats($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function computeStats(): array
    {
        $service = app(EventStoreService::class);
        $domainMap = $service->getDomainTableMap();
        $domainCounts = $service->getPerDomainEventCounts();

        $domainsWithSnapshots = 0;
        $domainsTotal = 0;

        foreach ($domainMap as $domain => $tables) {
            $domainsTotal++;
            if ($tables['snapshot_table'] !== null) {
                $domainsWithSnapshots++;
            }
        }

        $topDomains = array_slice($domainCounts, 0, 5, true);

        return [
            'domains_total'          => $domainsTotal,
            'domains_with_snapshots' => $domainsWithSnapshots,
            'top_domains'            => $topDomains,
            'total_domain_events'    => array_sum($domainCounts),
        ];
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<Stat>
     */
    private function buildStats(array $data): array
    {
        $domainsTotal = (int) ($data['domains_total'] ?? 0);
        $domainsWithSnapshots = (int) ($data['domains_with_snapshots'] ?? 0);

        /** @var array<string, int> $topDomains */
        $topDomains = $data['top_domains'] ?? [];
        $topParts = [];
        foreach ($topDomains as $domain => $count) {
            $topParts[] = "{$domain}: " . number_format($count);
        }
        $topDescription = implode(', ', $topParts) ?: 'No events';

        $snapshotCoverage = $domainsTotal > 0
            ? (int) round(($domainsWithSnapshots / $domainsTotal) * 100)
            : 0;

        return [
            Stat::make('Domains Tracked', (string) $domainsTotal)
                ->description('Event-sourced domains')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('primary'),

            Stat::make('Snapshot Coverage', "{$domainsWithSnapshots}/{$domainsTotal}")
                ->description("{$snapshotCoverage}% of domains have snapshots")
                ->descriptionIcon('heroicon-m-camera')
                ->color($snapshotCoverage >= 50 ? 'success' : 'warning'),

            Stat::make('Top Domains', $topDescription !== 'No events' ? (string) count($topDomains) . ' active' : 'None')
                ->description($topDescription)
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),
        ];
    }
}
