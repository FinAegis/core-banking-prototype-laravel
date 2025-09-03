<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Repositories;

use App\Domain\Compliance\Aggregates\TransactionMonitoringAggregate;
use App\Domain\Compliance\Models\TransactionMonitoring;
use Exception;
use Illuminate\Support\Collection;

class TransactionMonitoringRepository
{
    public function save(TransactionMonitoringAggregate $aggregate): void
    {
        // Spatie Event Sourcing handles this automatically when calling persist()
        $aggregate->persist();
    }

    public function find(string $transactionId): ?TransactionMonitoringAggregate
    {
        // Use Spatie's retrieve method to get the aggregate with all its events
        $aggregate = TransactionMonitoringAggregate::retrieve($transactionId);

        // Check if the aggregate actually exists (has events)
        try {
            // This will be empty if no events exist for this aggregate
            $aggregateTransactionId = $aggregate->getTransactionId();
            if (empty($aggregateTransactionId)) {
                return null;
            }
        } catch (Exception $e) {
            return null;
        }

        return $aggregate;
    }

    public function findByStatus(string $status): Collection
    {
        // Get monitoring records from the projection/read model
        return TransactionMonitoring::where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findFlaggedTransactions(): Collection
    {
        return $this->findByStatus('flagged');
    }

    public function findHighRiskTransactions(float $minScore = 75.0): Collection
    {
        return TransactionMonitoring::where('risk_score', '>=', $minScore)
            ->orderBy('risk_score', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByRiskLevel(string $level): Collection
    {
        return TransactionMonitoring::where('risk_level', $level)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function search(array $criteria): Collection
    {
        $query = TransactionMonitoring::query();

        if (isset($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        if (isset($criteria['risk_level'])) {
            $query->where('risk_level', $criteria['risk_level']);
        }

        if (isset($criteria['min_risk_score'])) {
            $query->where('risk_score', '>=', $criteria['min_risk_score']);
        }

        if (isset($criteria['max_risk_score'])) {
            $query->where('risk_score', '<=', $criteria['max_risk_score']);
        }

        if (isset($criteria['transaction_id'])) {
            $query->where('transaction_id', $criteria['transaction_id']);
        }

        if (isset($criteria['from_date'])) {
            $query->where('created_at', '>=', $criteria['from_date']);
        }

        if (isset($criteria['to_date'])) {
            $query->where('created_at', '<=', $criteria['to_date']);
        }

        if (isset($criteria['has_patterns']) && $criteria['has_patterns']) {
            $query->whereNotNull('patterns')
                  ->where('patterns', '!=', '[]');
        }

        return $query->orderBy('risk_score', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->get();
    }

    public function getStatistics(): array
    {
        return [
            'total'              => TransactionMonitoring::count(),
            'flagged'            => TransactionMonitoring::where('status', 'flagged')->count(),
            'cleared'            => TransactionMonitoring::where('status', 'cleared')->count(),
            'analyzing'          => TransactionMonitoring::where('status', 'analyzing')->count(),
            'high_risk'          => TransactionMonitoring::where('risk_level', 'high')->count(),
            'critical_risk'      => TransactionMonitoring::where('risk_level', 'critical')->count(),
            'average_risk_score' => TransactionMonitoring::avg('risk_score') ?? 0,
            'max_risk_score'     => TransactionMonitoring::max('risk_score') ?? 0,
        ];
    }

    public function getRecentMonitoringActivity(int $limit = 20): Collection
    {
        return TransactionMonitoring::orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function findTransactionsWithPatterns(string $patternType): Collection
    {
        return TransactionMonitoring::whereJsonContains('patterns', ['type' => $patternType])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
