<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Payment\Models\PaymentRequest;
use App\Domain\Banking\Models\BankTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TransactionStatusController extends Controller
{
    /**
     * Display the transaction status tracking page
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $accounts = $user->accounts()->with('balances.asset')->get();
        
        // Get filter parameters
        $filters = [
            'status' => $request->get('status', 'all'),
            'type' => $request->get('type', 'all'),
            'account' => $request->get('account', 'all'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];
        
        // Get pending transactions
        $pendingTransactions = $this->getPendingTransactions($user, $filters);
        
        // Get recent completed transactions
        $completedTransactions = $this->getCompletedTransactions($user, $filters);
        
        // Get transaction statistics
        $statistics = $this->getTransactionStatistics($user);
        
        return Inertia::render('Transactions/StatusTracking', [
            'accounts' => $accounts,
            'pendingTransactions' => $pendingTransactions,
            'completedTransactions' => $completedTransactions,
            'statistics' => $statistics,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get details for a specific transaction
     */
    public function show($transactionId)
    {
        $user = Auth::user();
        
        // Try to find the transaction in different tables
        $transaction = $this->findTransaction($transactionId, $user);
        
        if (!$transaction) {
            abort(404, 'Transaction not found');
        }
        
        // Get transaction timeline/history
        $timeline = $this->getTransactionTimeline($transaction);
        
        // Get related transactions (if any)
        $relatedTransactions = $this->getRelatedTransactions($transaction);
        
        return Inertia::render('Transactions/StatusDetail', [
            'transaction' => $transaction,
            'timeline' => $timeline,
            'relatedTransactions' => $relatedTransactions,
        ]);
    }
    
    /**
     * Get real-time status update for a transaction
     */
    public function status($transactionId)
    {
        $user = Auth::user();
        $transaction = $this->findTransaction($transactionId, $user);
        
        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }
        
        // Check for status updates
        $currentStatus = $this->getCurrentStatus($transaction);
        $estimatedCompletion = $this->getEstimatedCompletion($transaction);
        
        return response()->json([
            'id' => $transaction->id,
            'status' => $currentStatus,
            'estimated_completion' => $estimatedCompletion,
            'last_updated' => $transaction->updated_at,
            'can_cancel' => $this->canCancelTransaction($transaction),
            'can_retry' => $this->canRetryTransaction($transaction),
        ]);
    }
    
    /**
     * Cancel a pending transaction
     */
    public function cancel($transactionId)
    {
        $user = Auth::user();
        $transaction = $this->findTransaction($transactionId, $user);
        
        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }
        
        if (!$this->canCancelTransaction($transaction)) {
            return response()->json(['error' => 'Transaction cannot be cancelled'], 400);
        }
        
        DB::beginTransaction();
        try {
            // Update transaction status
            $transaction->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
            ]);
            
            // Reverse any holds or pending operations
            $this->reverseTransaction($transaction);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Transaction cancelled successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to cancel transaction'], 500);
        }
    }
    
    /**
     * Retry a failed transaction
     */
    public function retry($transactionId)
    {
        $user = Auth::user();
        $transaction = $this->findTransaction($transactionId, $user);
        
        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }
        
        if (!$this->canRetryTransaction($transaction)) {
            return response()->json(['error' => 'Transaction cannot be retried'], 400);
        }
        
        DB::beginTransaction();
        try {
            // Create a new transaction based on the failed one
            $newTransaction = $this->createRetryTransaction($transaction);
            
            // Mark original as retried
            $transaction->update([
                'retried_at' => now(),
                'retry_transaction_id' => $newTransaction->id,
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Transaction retry initiated',
                'new_transaction_id' => $newTransaction->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to retry transaction'], 500);
        }
    }
    
    /**
     * Get pending transactions for a user
     */
    private function getPendingTransactions($user, $filters)
    {
        $query = DB::table('transactions')
            ->join('accounts', 'transactions.account_uuid', '=', 'accounts.uuid')
            ->where('accounts.user_uuid', $user->uuid)
            ->whereIn('transactions.status', ['pending', 'processing', 'hold']);
        
        // Apply filters
        if ($filters['type'] !== 'all') {
            $query->where('transactions.type', $filters['type']);
        }
        
        if ($filters['account'] !== 'all') {
            $query->where('transactions.account_uuid', $filters['account']);
        }
        
        if ($filters['date_from']) {
            $query->where('transactions.created_at', '>=', $filters['date_from']);
        }
        
        if ($filters['date_to']) {
            $query->where('transactions.created_at', '<=', $filters['date_to']);
        }
        
        return $query->select(
                'transactions.*',
                'accounts.name as account_name'
            )
            ->orderBy('transactions.created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($transaction) {
                $transaction->estimated_completion = $this->calculateEstimatedCompletion($transaction);
                $transaction->progress_percentage = $this->calculateProgressPercentage($transaction);
                return $transaction;
            });
    }
    
    /**
     * Get completed transactions for a user
     */
    private function getCompletedTransactions($user, $filters)
    {
        $query = DB::table('transactions')
            ->join('accounts', 'transactions.account_uuid', '=', 'accounts.uuid')
            ->where('accounts.user_uuid', $user->uuid)
            ->whereIn('transactions.status', ['completed', 'failed', 'cancelled']);
        
        // Apply filters
        if ($filters['status'] !== 'all' && $filters['status'] !== 'pending') {
            $query->where('transactions.status', $filters['status']);
        }
        
        if ($filters['type'] !== 'all') {
            $query->where('transactions.type', $filters['type']);
        }
        
        if ($filters['account'] !== 'all') {
            $query->where('transactions.account_uuid', $filters['account']);
        }
        
        if ($filters['date_from']) {
            $query->where('transactions.created_at', '>=', $filters['date_from']);
        }
        
        if ($filters['date_to']) {
            $query->where('transactions.created_at', '<=', $filters['date_to']);
        }
        
        return $query->select(
                'transactions.*',
                'accounts.name as account_name'
            )
            ->orderBy('transactions.created_at', 'desc')
            ->limit(100)
            ->get();
    }
    
    /**
     * Get transaction statistics
     */
    private function getTransactionStatistics($user)
    {
        $stats = DB::table('transactions')
            ->join('accounts', 'transactions.account_uuid', '=', 'accounts.uuid')
            ->where('accounts.user_uuid', $user->uuid)
            ->where('transactions.created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as processing'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'),
                DB::raw('AVG(CASE WHEN status = "completed" THEN TIMESTAMPDIFF(SECOND, created_at, updated_at) END) as avg_completion_time')
            )
            ->first();
        
        // Calculate success rate
        $stats->success_rate = $stats->total > 0 
            ? round(($stats->completed / $stats->total) * 100, 1) 
            : 0;
        
        // Format average completion time
        $stats->avg_completion_time_formatted = $stats->avg_completion_time 
            ? $this->formatDuration($stats->avg_completion_time)
            : 'N/A';
        
        return $stats;
    }
    
    /**
     * Find a transaction across different tables
     */
    private function findTransaction($transactionId, $user)
    {
        // Check main transactions table
        $transaction = DB::table('transactions')
            ->join('accounts', 'transactions.account_uuid', '=', 'accounts.uuid')
            ->where('accounts.user_uuid', $user->uuid)
            ->where('transactions.id', $transactionId)
            ->select('transactions.*', 'accounts.name as account_name')
            ->first();
        
        if ($transaction) {
            $transaction->source = 'transaction';
            return $transaction;
        }
        
        // Check payment requests
        $payment = DB::table('payment_requests')
            ->join('accounts', 'payment_requests.account_uuid', '=', 'accounts.uuid')
            ->where('accounts.user_uuid', $user->uuid)
            ->where('payment_requests.id', $transactionId)
            ->select('payment_requests.*', 'accounts.name as account_name')
            ->first();
        
        if ($payment) {
            $payment->source = 'payment';
            return $payment;
        }
        
        // Check bank transfers
        $transfer = DB::table('bank_transfers')
            ->where('user_uuid', $user->uuid)
            ->where('id', $transactionId)
            ->first();
        
        if ($transfer) {
            $transfer->source = 'bank_transfer';
            return $transfer;
        }
        
        return null;
    }
    
    /**
     * Get transaction timeline
     */
    private function getTransactionTimeline($transaction)
    {
        $timeline = [];
        
        // Created event
        $timeline[] = [
            'event' => 'created',
            'description' => 'Transaction initiated',
            'timestamp' => $transaction->created_at,
            'status' => 'completed',
        ];
        
        // Processing events (from metadata or logs)
        if (isset($transaction->metadata)) {
            $metadata = is_string($transaction->metadata) 
                ? json_decode($transaction->metadata, true) 
                : $transaction->metadata;
            
            if (isset($metadata['timeline'])) {
                foreach ($metadata['timeline'] as $event) {
                    $timeline[] = $event;
                }
            }
        }
        
        // Current status
        if ($transaction->status === 'processing') {
            $timeline[] = [
                'event' => 'processing',
                'description' => 'Transaction is being processed',
                'timestamp' => $transaction->updated_at,
                'status' => 'active',
            ];
        }
        
        // Completion event
        if (in_array($transaction->status, ['completed', 'failed', 'cancelled'])) {
            $timeline[] = [
                'event' => $transaction->status,
                'description' => $this->getStatusDescription($transaction->status),
                'timestamp' => $transaction->updated_at,
                'status' => $transaction->status === 'completed' ? 'completed' : 'error',
            ];
        }
        
        return $timeline;
    }
    
    /**
     * Get related transactions
     */
    private function getRelatedTransactions($transaction)
    {
        $related = [];
        
        // Check for parent/child relationships
        if (isset($transaction->parent_transaction_id)) {
            $parent = $this->findTransaction($transaction->parent_transaction_id, Auth::user());
            if ($parent) {
                $related[] = [
                    'type' => 'parent',
                    'transaction' => $parent,
                ];
            }
        }
        
        // Check for retry transactions
        if (isset($transaction->retry_transaction_id)) {
            $retry = $this->findTransaction($transaction->retry_transaction_id, Auth::user());
            if ($retry) {
                $related[] = [
                    'type' => 'retry',
                    'transaction' => $retry,
                ];
            }
        }
        
        // Check for reversal transactions
        if (isset($transaction->reference)) {
            $reversals = DB::table('transactions')
                ->where('reference', 'like', 'REV-' . $transaction->reference . '%')
                ->limit(5)
                ->get();
            
            foreach ($reversals as $reversal) {
                $related[] = [
                    'type' => 'reversal',
                    'transaction' => $reversal,
                ];
            }
        }
        
        return $related;
    }
    
    /**
     * Get current status of a transaction
     */
    private function getCurrentStatus($transaction)
    {
        // For bank transfers, check with bank API
        if ($transaction->source === 'bank_transfer' && isset($transaction->external_reference)) {
            // In production, this would call the bank API
            // For now, return the stored status
        }
        
        return $transaction->status;
    }
    
    /**
     * Get estimated completion time
     */
    private function getEstimatedCompletion($transaction)
    {
        if ($transaction->status === 'completed') {
            return null;
        }
        
        // Different estimates based on transaction type
        $estimates = [
            'deposit' => ['card' => 5, 'bank' => 1440], // 5 min for card, 1 day for bank
            'withdrawal' => ['standard' => 2880, 'express' => 60], // 2 days standard, 1 hour express
            'transfer' => ['internal' => 1, 'external' => 30], // 1 min internal, 30 min external
            'exchange' => ['instant' => 1, 'market' => 5], // 1 min instant, 5 min market rate
        ];
        
        $type = $transaction->type ?? 'transfer';
        $subtype = $transaction->subtype ?? 'standard';
        
        $estimateMinutes = $estimates[$type][$subtype] ?? 60;
        
        return $transaction->created_at->addMinutes($estimateMinutes);
    }
    
    /**
     * Calculate estimated completion time
     */
    private function calculateEstimatedCompletion($transaction)
    {
        $estimate = $this->getEstimatedCompletion($transaction);
        return $estimate ? $estimate->toIso8601String() : null;
    }
    
    /**
     * Calculate progress percentage
     */
    private function calculateProgressPercentage($transaction)
    {
        if ($transaction->status === 'completed') {
            return 100;
        }
        
        if ($transaction->status === 'failed' || $transaction->status === 'cancelled') {
            return 0;
        }
        
        // Calculate based on time elapsed
        $created = \Carbon\Carbon::parse($transaction->created_at);
        $estimate = $this->getEstimatedCompletion($transaction);
        
        if (!$estimate) {
            return 50; // Default to 50% if no estimate
        }
        
        $totalMinutes = $created->diffInMinutes($estimate);
        $elapsedMinutes = $created->diffInMinutes(now());
        
        $percentage = min(95, ($elapsedMinutes / $totalMinutes) * 100);
        
        return round($percentage);
    }
    
    /**
     * Check if transaction can be cancelled
     */
    private function canCancelTransaction($transaction)
    {
        // Only pending transactions can be cancelled
        if (!in_array($transaction->status, ['pending', 'hold'])) {
            return false;
        }
        
        // Check transaction type
        if (in_array($transaction->type, ['deposit', 'internal_transfer'])) {
            return false; // These complete too quickly
        }
        
        // Check if too much time has passed
        $created = \Carbon\Carbon::parse($transaction->created_at);
        if ($created->diffInMinutes(now()) > 30) {
            return false; // After 30 minutes, likely already processing
        }
        
        return true;
    }
    
    /**
     * Check if transaction can be retried
     */
    private function canRetryTransaction($transaction)
    {
        // Only failed transactions can be retried
        if ($transaction->status !== 'failed') {
            return false;
        }
        
        // Check if already retried
        if (isset($transaction->retried_at)) {
            return false;
        }
        
        // Check retry limit (e.g., within 24 hours)
        $failed = \Carbon\Carbon::parse($transaction->updated_at);
        if ($failed->diffInHours(now()) > 24) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Reverse a transaction
     */
    private function reverseTransaction($transaction)
    {
        // This would implement the actual reversal logic
        // For now, just log the reversal
        \Log::info('Transaction reversed', [
            'transaction_id' => $transaction->id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
        ]);
    }
    
    /**
     * Create a retry transaction
     */
    private function createRetryTransaction($originalTransaction)
    {
        // Clone the transaction with a new ID and reset status
        $data = (array) $originalTransaction;
        unset($data['id'], $data['created_at'], $data['updated_at']);
        
        $data['status'] = 'pending';
        $data['parent_transaction_id'] = $originalTransaction->id;
        $data['reference'] = 'RETRY-' . $originalTransaction->reference;
        
        return DB::table('transactions')->insertGetId($data);
    }
    
    /**
     * Format duration in seconds to human-readable
     */
    private function formatDuration($seconds)
    {
        if ($seconds < 60) {
            return round($seconds) . ' seconds';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . ' minutes';
        } elseif ($seconds < 86400) {
            return round($seconds / 3600, 1) . ' hours';
        } else {
            return round($seconds / 86400, 1) . ' days';
        }
    }
    
    /**
     * Get status description
     */
    private function getStatusDescription($status)
    {
        $descriptions = [
            'completed' => 'Transaction completed successfully',
            'failed' => 'Transaction failed',
            'cancelled' => 'Transaction was cancelled',
            'pending' => 'Transaction is pending',
            'processing' => 'Transaction is being processed',
            'hold' => 'Transaction is on hold',
        ];
        
        return $descriptions[$status] ?? 'Unknown status';
    }
}