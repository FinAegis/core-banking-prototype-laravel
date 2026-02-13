<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use App\Domain\Shared\EventSourcing\EventRouterInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventStoreService
{
    public function __construct(
        private readonly ?EventRouterInterface $eventRouter = null,
    ) {
    }

    /**
     * Domain-to-table mapping for event sourcing infrastructure.
     *
     * When the EventRouter is available and domain partitioning is active,
     * event tables are resolved dynamically. Otherwise falls back to stored_events.
     *
     * @return array<string, array{event_table: string, snapshot_table: string|null}>
     */
    public function getDomainTableMap(): array
    {
        $useRouter = $this->eventRouter && config('event-store.partitioning.strategy') === 'domain';
        $eventRouter = $this->eventRouter;

        $resolveTable = fn (string $domain): string => $useRouter && $eventRouter !== null
            ? $eventRouter->resolveTableForDomain($domain)
            : 'stored_events';

        return [
            'Account' => [
                'event_table'    => $resolveTable('Account'),
                'snapshot_table' => 'transaction_snapshots',
            ],
            'Transfer' => [
                'event_table'    => $resolveTable('Account'),
                'snapshot_table' => 'transfer_snapshots',
            ],
            'Stablecoin' => [
                'event_table'    => $resolveTable('Stablecoin'),
                'snapshot_table' => 'stablecoin_snapshots',
            ],
            'Collateral' => [
                'event_table'    => $resolveTable('Stablecoin'),
                'snapshot_table' => 'collateral_position_snapshots',
            ],
            'Treasury' => [
                'event_table'    => $resolveTable('Treasury'),
                'snapshot_table' => 'treasury_snapshots',
            ],
            'Portfolio' => [
                'event_table'    => $resolveTable('Treasury'),
                'snapshot_table' => 'portfolio_snapshots',
            ],
            'Monitoring' => [
                'event_table'    => $resolveTable('Monitoring'),
                'snapshot_table' => 'monitoring_metrics_snapshots',
            ],
            'Compliance' => [
                'event_table'    => $resolveTable('Compliance'),
                'snapshot_table' => 'compliance_snapshots',
            ],
            'Exchange' => [
                'event_table'    => $resolveTable('Exchange'),
                'snapshot_table' => null,
            ],
            'Lending' => [
                'event_table'    => $resolveTable('Lending'),
                'snapshot_table' => null,
            ],
            'Payment' => [
                'event_table'    => $resolveTable('Payment'),
                'snapshot_table' => null,
            ],
            'Wallet' => [
                'event_table'    => $resolveTable('Wallet'),
                'snapshot_table' => null,
            ],
            'AgentProtocol' => [
                'event_table'    => $resolveTable('AgentProtocol'),
                'snapshot_table' => null,
            ],
            'AI' => [
                'event_table'    => $resolveTable('AI'),
                'snapshot_table' => null,
            ],
            'Batch' => [
                'event_table'    => $resolveTable('Batch'),
                'snapshot_table' => null,
            ],
            'Cgo' => [
                'event_table'    => $resolveTable('Cgo'),
                'snapshot_table' => null,
            ],
            'User' => [
                'event_table'    => $resolveTable('User'),
                'snapshot_table' => null,
            ],
            'Performance' => [
                'event_table'    => $resolveTable('Performance'),
                'snapshot_table' => null,
            ],
            'Product' => [
                'event_table'    => $resolveTable('Product'),
                'snapshot_table' => null,
            ],
            'Asset' => [
                'event_table'    => $resolveTable('Asset'),
                'snapshot_table' => null,
            ],
            'Mobile' => [
                'event_table'    => $resolveTable('Mobile'),
                'snapshot_table' => null,
            ],
        ];
    }

    /**
     * Get statistics for a specific event table.
     *
     * @return array<string, mixed>
     */
    public function getTableStats(string $tableName): array
    {
        if (! Schema::hasTable($tableName)) {
            return [
                'table'        => $tableName,
                'exists'       => false,
                'total_events' => 0,
            ];
        }

        $totalEvents = DB::table($tableName)->count();
        $uniqueAggregates = DB::table($tableName)
            ->whereNotNull('aggregate_uuid')
            ->distinct('aggregate_uuid')
            ->count('aggregate_uuid');

        $oldest = DB::table($tableName)->min('created_at');
        $newest = DB::table($tableName)->max('created_at');

        $distribution = DB::table($tableName)
            ->select('event_class', DB::raw('COUNT(*) as count'))
            ->groupBy('event_class')
            ->orderByDesc('count')
            ->limit(20)
            ->pluck('count', 'event_class')
            ->toArray();

        return [
            'table'                    => $tableName,
            'exists'                   => true,
            'total_events'             => $totalEvents,
            'unique_aggregates'        => $uniqueAggregates,
            'oldest_event'             => $oldest,
            'newest_event'             => $newest,
            'event_class_distribution' => $distribution,
        ];
    }

    /**
     * Get aggregated statistics across all event tables.
     *
     * @return array<string, mixed>
     */
    public function getAllStats(): array
    {
        $cacheKey = 'event_store:all_stats';

        return Cache::remember($cacheKey, 30, function () {
            $domainMap = $this->getDomainTableMap();
            $stats = [];
            $totalEvents = 0;
            $totalAggregates = 0;
            $totalSnapshots = 0;

            $eventTables = array_unique(array_column($domainMap, 'event_table'));

            foreach ($eventTables as $table) {
                $tableStats = $this->getTableStats($table);
                $stats['event_tables'][$table] = $tableStats;
                $totalEvents += (int) $tableStats['total_events'];
                $totalAggregates += (int) ($tableStats['unique_aggregates'] ?? 0);
            }

            $snapshotTables = array_filter(array_unique(array_column($domainMap, 'snapshot_table')));
            foreach ($snapshotTables as $table) {
                $snapshotStats = $this->getSnapshotStats($table);
                $stats['snapshot_tables'][$table] = $snapshotStats;
                $totalSnapshots += (int) ($snapshotStats['total_snapshots'] ?? 0);
            }

            $todayCount = 0;
            foreach ($eventTables as $table) {
                if (Schema::hasTable($table)) {
                    $todayCount += DB::table($table)
                        ->whereDate('created_at', now()->toDateString())
                        ->count();
                }
            }

            $stats['summary'] = [
                'total_events'     => $totalEvents,
                'total_aggregates' => $totalAggregates,
                'total_snapshots'  => $totalSnapshots,
                'events_today'     => $todayCount,
                'domain_count'     => count($domainMap),
            ];

            return $stats;
        });
    }

    /**
     * Count events in a specific table within a date range.
     */
    public function countEvents(string $tableName, ?string $from = null, ?string $to = null): int
    {
        if (! Schema::hasTable($tableName)) {
            return 0;
        }

        $query = DB::table($tableName);

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        return $query->count();
    }

    /**
     * Get snapshot statistics for a specific table.
     *
     * @return array<string, mixed>
     */
    public function getSnapshotStats(string $snapshotTable): array
    {
        if (! Schema::hasTable($snapshotTable)) {
            return [
                'table'  => $snapshotTable,
                'exists' => false,
            ];
        }

        $totalSnapshots = DB::table($snapshotTable)->count();
        $uniqueAggregates = DB::table($snapshotTable)
            ->distinct('aggregate_uuid')
            ->count('aggregate_uuid');
        $oldest = DB::table($snapshotTable)->min('created_at');
        $newest = DB::table($snapshotTable)->max('created_at');

        return [
            'table'             => $snapshotTable,
            'exists'            => true,
            'total_snapshots'   => $totalSnapshots,
            'unique_aggregates' => $uniqueAggregates,
            'oldest_snapshot'   => $oldest,
            'newest_snapshot'   => $newest,
        ];
    }

    /**
     * Clean up old snapshots, keeping the latest per aggregate UUID.
     */
    public function cleanupSnapshots(string $snapshotTable, int $retainDays): int
    {
        if (! Schema::hasTable($snapshotTable)) {
            return 0;
        }

        $cutoffDate = now()->subDays($retainDays)->toDateTimeString();

        // Single query: delete snapshots older than cutoff that are NOT the latest per aggregate
        $latestIdsSubquery = DB::table($snapshotTable)
            ->selectRaw('MAX(id) as id')
            ->groupBy('aggregate_uuid');

        return DB::table($snapshotTable)
            ->where('created_at', '<', $cutoffDate)
            ->whereNotIn('id', $latestIdsSubquery)
            ->delete();
    }

    /**
     * Discover all event tables that exist in the database.
     *
     * @return array<string>
     */
    public function discoverEventTables(): array
    {
        $domainMap = $this->getDomainTableMap();
        $tables = array_unique(array_column($domainMap, 'event_table'));

        return array_values(array_filter($tables, fn (string $table) => Schema::hasTable($table)));
    }

    /**
     * Resolve the event table for a given domain.
     *
     * When the EventRouter is available, delegates to it directly.
     * Otherwise falls back to the domain table map.
     */
    public function resolveEventTable(string $domain): ?string
    {
        $map = $this->getDomainTableMap();

        // Validate domain exists before resolving
        if (! isset($map[$domain])) {
            return null;
        }

        if ($this->eventRouter && config('event-store.partitioning.strategy') === 'domain') {
            return $this->eventRouter->resolveTableForDomain($domain);
        }

        return $map[$domain]['event_table'] ?? null;
    }

    /**
     * Resolve the snapshot table for a given domain.
     */
    public function resolveSnapshotTable(string $domain): ?string
    {
        $map = $this->getDomainTableMap();

        return $map[$domain]['snapshot_table'] ?? null;
    }

    /**
     * Resolve domain name by event table name (reverse lookup).
     */
    public function resolveDomainByEventTable(string $eventTable): ?string
    {
        $map = $this->getDomainTableMap();

        foreach ($map as $domain => $tables) {
            if ($tables['event_table'] === $eventTable) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * Get events per minute for a given table over the last N minutes.
     *
     * @return array<string, int>
     */
    public function getEventThroughput(string $tableName, int $minutes = 60): array
    {
        if (! Schema::hasTable($tableName)) {
            return [];
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $minuteExpr = DB::raw("strftime('%Y-%m-%d %H:%M', created_at) as minute");
        } else {
            $minuteExpr = DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as minute");
        }

        $results = DB::table($tableName)
            ->select($minuteExpr, DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->groupBy('minute')
            ->orderBy('minute')
            ->pluck('count', 'minute')
            ->toArray();

        return $results;
    }

    /**
     * Get per-domain event counts from the event class map.
     *
     * @return array<string, int>
     */
    public function getPerDomainEventCounts(): array
    {
        if (! Schema::hasTable('stored_events')) {
            return [];
        }

        $eventClassMap = config('event-sourcing.event_class_map', []);
        $domainCounts = [];

        foreach ($eventClassMap as $alias => $className) {
            // Extract domain from class name (e.g., App\Domain\Account\Events\... → Account)
            if (preg_match('/App\\\\Domain\\\\([^\\\\]+)\\\\/', $className, $matches)) {
                $domain = $matches[1];
                if (! isset($domainCounts[$domain])) {
                    $domainCounts[$domain] = 0;
                }
            }
        }

        // Count events per alias group
        $eventCounts = DB::table('stored_events')
            ->select('event_class', DB::raw('COUNT(*) as count'))
            ->groupBy('event_class')
            ->pluck('count', 'event_class')
            ->toArray();

        foreach ($eventClassMap as $alias => $className) {
            if (preg_match('/App\\\\Domain\\\\([^\\\\]+)\\\\/', $className, $matches)) {
                $domain = $matches[1];
                // The event_class column stores the alias, not the full class name
                $domainCounts[$domain] = ($domainCounts[$domain] ?? 0) + ($eventCounts[$alias] ?? 0);
            }
        }

        arsort($domainCounts);

        return $domainCounts;
    }
}
