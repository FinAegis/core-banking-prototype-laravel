<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Models\AgentTransaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Workflow\Activity;

class GetRecentTransactionsActivity extends Activity
{
    public function execute(
        string $agentId,
        int $days = 30,
        array $options = []
    ): array {
        try {
            $startDate = Carbon::now()->subDays($days);

            $query = AgentTransaction::where('from_agent_id', $agentId)
                ->where('created_at', '>=', $startDate)
                ->orderBy('created_at', 'desc');

            // Apply optional filters
            if (isset($options['status'])) {
                $query->where('status', $options['status']);
            }

            if (isset($options['min_amount'])) {
                $query->where('amount', '>=', $options['min_amount']);
            }

            if (isset($options['max_amount'])) {
                $query->where('amount', '<=', $options['max_amount']);
            }

            if (isset($options['transaction_type'])) {
                $query->where('type', $options['transaction_type']);
            }

            // Limit results if specified
            $limit = $options['limit'] ?? 1000;
            $query->limit($limit);

            $transactions = $query->get();

            // Transform transactions to array
            $transactionData = $transactions->map(function ($transaction) {
                return [
                    'id'                 => $transaction->id,
                    'transaction_id'     => $transaction->transaction_id,
                    'amount'             => $transaction->amount,
                    'currency'           => $transaction->currency,
                    'status'             => $transaction->status,
                    'transaction_type'   => $transaction->transaction_type,
                    'recipient_id'       => $transaction->recipient_id,
                    'sender_id'          => $transaction->sender_id,
                    'metadata'           => $transaction->metadata,
                    'created_at'         => $transaction->created_at->toIso8601String(),
                    'risk_score'         => $transaction->risk_score,
                    'fraud_check_status' => $transaction->fraud_check_status,
                ];
            })->toArray();

            // Calculate statistics
            $stats = [
                'total_transactions' => count($transactionData),
                'total_amount'       => $transactions->sum('amount'),
                'average_amount'     => $transactions->avg('amount'),
                'status_breakdown'   => $transactions->groupBy('status')->map->count(),
                'type_breakdown'     => $transactions->groupBy('transaction_type')->map->count(),
            ];

            Log::info('Retrieved recent transactions for agent', [
                'agent_id'   => $agentId,
                'days'       => $days,
                'count'      => count($transactionData),
                'date_range' => [
                    'from' => $startDate->toIso8601String(),
                    'to'   => now()->toIso8601String(),
                ],
            ]);

            return [
                'success'      => true,
                'agent_id'     => $agentId,
                'transactions' => $transactionData,
                'statistics'   => $stats,
                'period_days'  => $days,
                'retrieved_at' => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to retrieve recent transactions', [
                'agent_id' => $agentId,
                'error'    => $e->getMessage(),
                'days'     => $days,
            ]);

            return [
                'success'      => false,
                'error'        => $e->getMessage(),
                'agent_id'     => $agentId,
                'transactions' => [],
                'failed_at'    => now()->toIso8601String(),
            ];
        }
    }
}
