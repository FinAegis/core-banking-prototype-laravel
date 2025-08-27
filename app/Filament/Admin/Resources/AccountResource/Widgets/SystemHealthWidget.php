<?php

namespace App\Filament\Admin\Resources\AccountResource\Widgets;

use App\Domain\Account\Models\Transaction as TransactionEvent;
use Exception;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemHealthWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected static ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        return [
            Stat::make('System Status', $this->getSystemStatus())
                ->description($this->getSystemDescription())
                ->descriptionIcon($this->getSystemIcon())
                ->color($this->getSystemColor())
                ->chart($this->getUptimeChart()),

            Stat::make('Transaction Processing', $this->getTransactionRate())
                ->description('per minute')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart($this->getTransactionRateChart()),

            Stat::make('Cache Performance', $this->getCacheHitRate())
                ->description('hit rate')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color($this->getCacheColor())
                ->chart($this->getCachePerformanceChart()),

            Stat::make('Queue Health', $this->getQueueStatus())
                ->description($this->getQueueDescription())
                ->descriptionIcon('heroicon-m-queue-list')
                ->color($this->getQueueColor()),
        ];
    }

    private function getSystemStatus(): string
    {
        try {
            // Check database connectivity
            DB::select('SELECT 1');

            // Check Redis connectivity
            Redis::ping();

            return 'Operational';
        } catch (Exception $e) {
            return 'Degraded';
        }
    }

    private function getSystemDescription(): string
    {
        $issues = [];

        try {
            DB::select('SELECT 1');
        } catch (Exception $e) {
            $issues[] = 'Database';
        }

        try {
            Redis::ping();
        } catch (Exception $e) {
            $issues[] = 'Redis';
        }

        return empty($issues) ? 'All systems operational' : 'Issues: ' . implode(', ', $issues);
    }

    private function getSystemIcon(): string
    {
        return $this->getSystemStatus() === 'Operational' ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle';
    }

    private function getSystemColor(): string
    {
        return $this->getSystemStatus() === 'Operational' ? 'success' : 'warning';
    }

    private function getTransactionRate(): string
    {
        $rate = TransactionEvent::whereIn(
            'event_class',
            [
                'App\\Domain\\Account\\Events\\MoneyAdded',
                'App\\Domain\\Account\\Events\\MoneySubtracted',
                'App\\Domain\\Account\\Events\\MoneyTransferred',
            ]
        )
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        return number_format($rate);
    }

    private function getCacheHitRate(): string
    {
        $stats = Cache::store('redis')->getStore()->getRedis()->info('stats');

        $hits = $stats['keyspace_hits'] ?? 0;
        $misses = $stats['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        if ($total === 0) {
            return '0%';
        }

        return round(($hits / $total) * 100) . '%';
    }

    private function getCacheColor(): string
    {
        $rate = (int) str_replace('%', '', $this->getCacheHitRate());

        if ($rate >= 90) {
            return 'success';
        } elseif ($rate >= 70) {
            return 'warning';
        } else {
            return 'danger';
        }
    }

    private function getQueueStatus(): string
    {
        try {
            $queues = ['events', 'ledger', 'transactions', 'transfers'];
            $totalJobs = 0;

            foreach ($queues as $queue) {
                $totalJobs += Redis::llen("queues:{$queue}");
            }

            if ($totalJobs === 0) {
                return 'Idle';
            } elseif ($totalJobs < 100) {
                return 'Active';
            } else {
                return 'Busy';
            }
        } catch (Exception $e) {
            return 'Unknown';
        }
    }

    private function getQueueDescription(): string
    {
        try {
            $queues = ['events', 'ledger', 'transactions', 'transfers'];
            $totalJobs = 0;

            foreach ($queues as $queue) {
                $totalJobs += Redis::llen("queues:{$queue}");
            }

            return $totalJobs . ' jobs pending';
        } catch (Exception $e) {
            return 'Queue status unavailable';
        }
    }

    private function getQueueColor(): string
    {
        $status = $this->getQueueStatus();

        return match ($status) {
            'Idle'   => 'success',
            'Active' => 'info',
            'Busy'   => 'warning',
            default  => 'gray',
        };
    }

    private function getUptimeChart(): array
    {
        // Simulated uptime data for the last 7 days
        return [100, 100, 99.9, 100, 100, 99.8, 100];
    }

    private function getTransactionRateChart(): array
    {
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $count = TransactionEvent::whereIn(
                'event_class',
                [
                    'App\\Domain\\Account\\Events\\MoneyAdded',
                    'App\\Domain\\Account\\Events\\MoneySubtracted',
                    'App\\Domain\\Account\\Events\\MoneyTransferred',
                ]
            )
                ->where('created_at', '>=', now()->subMinutes($i + 1))
                ->where('created_at', '<', now()->subMinutes($i))
                ->count();
            $data[] = $count;
        }

        return $data;
    }

    private function getCachePerformanceChart(): array
    {
        // Simulated cache hit rate over the last 7 measurements
        return [85, 88, 92, 90, 91, 89, (int) str_replace('%', '', $this->getCacheHitRate())];
    }
}
