<?php

namespace App\Domain\Regulatory\Services;

use App\Domain\Regulatory\Models\RegulatoryThreshold;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ThresholdMonitoringService
{
    /**
     * Monitor transaction against all active thresholds
     */
    public function monitorTransaction(Transaction $transaction): Collection
    {
        $triggeredThresholds = collect();
        
        // Get applicable thresholds
        $thresholds = $this->getApplicableThresholds(
            RegulatoryThreshold::CATEGORY_TRANSACTION,
            $transaction->account->jurisdiction ?? 'US'
        );
        
        foreach ($thresholds as $threshold) {
            $context = $this->buildTransactionContext($transaction);
            
            if ($threshold->evaluate($context)) {
                $threshold->recordTrigger();
                $triggeredThresholds->push([
                    'threshold' => $threshold,
                    'context' => $context,
                    'triggered_at' => now(),
                ]);
                
                $this->handleThresholdTrigger($threshold, $transaction, $context);
            }
        }
        
        return $triggeredThresholds;
    }
    
    /**
     * Monitor customer activity against thresholds
     */
    public function monitorCustomer(User $user): Collection
    {
        $triggeredThresholds = collect();
        
        // Get applicable thresholds
        $thresholds = $this->getApplicableThresholds(
            RegulatoryThreshold::CATEGORY_CUSTOMER,
            $user->jurisdiction ?? 'US'
        );
        
        foreach ($thresholds as $threshold) {
            $context = $this->buildCustomerContext($user);
            
            if ($threshold->evaluate($context)) {
                $threshold->recordTrigger();
                $triggeredThresholds->push([
                    'threshold' => $threshold,
                    'context' => $context,
                    'triggered_at' => now(),
                ]);
                
                $this->handleThresholdTrigger($threshold, $user, $context);
            }
        }
        
        return $triggeredThresholds;
    }
    
    /**
     * Monitor account activity against thresholds
     */
    public function monitorAccount(Account $account): Collection
    {
        $triggeredThresholds = collect();
        
        // Get applicable thresholds
        $thresholds = $this->getApplicableThresholds(
            RegulatoryThreshold::CATEGORY_ACCOUNT,
            $account->jurisdiction ?? 'US'
        );
        
        foreach ($thresholds as $threshold) {
            $context = $this->buildAccountContext($account);
            
            if ($threshold->evaluate($context)) {
                $threshold->recordTrigger();
                $triggeredThresholds->push([
                    'threshold' => $threshold,
                    'context' => $context,
                    'triggered_at' => now(),
                ]);
                
                $this->handleThresholdTrigger($threshold, $account, $context);
            }
        }
        
        return $triggeredThresholds;
    }
    
    /**
     * Run aggregate monitoring for a time period
     */
    public function runAggregateMonitoring(Carbon $date): Collection
    {
        $triggeredThresholds = collect();
        
        // Get aggregate thresholds
        $thresholds = RegulatoryThreshold::active()
            ->byCategory(RegulatoryThreshold::CATEGORY_AGGREGATE)
            ->requireingAggregation()
            ->get();
        
        foreach ($thresholds as $threshold) {
            $aggregateData = $this->performAggregation($threshold, $date);
            
            foreach ($aggregateData as $aggregateKey => $context) {
                if ($threshold->evaluate($context)) {
                    $threshold->recordTrigger();
                    $triggeredThresholds->push([
                        'threshold' => $threshold,
                        'aggregate_key' => $aggregateKey,
                        'context' => $context,
                        'triggered_at' => now(),
                    ]);
                    
                    $this->handleAggregateThresholdTrigger($threshold, $aggregateKey, $context);
                }
            }
        }
        
        return $triggeredThresholds;
    }
    
    /**
     * Get applicable thresholds
     */
    protected function getApplicableThresholds(string $category, string $jurisdiction): Collection
    {
        return Cache::remember(
            "thresholds_{$category}_{$jurisdiction}",
            300, // 5 minutes
            function () use ($category, $jurisdiction) {
                return RegulatoryThreshold::active()
                    ->byCategory($category)
                    ->where(function ($query) use ($jurisdiction) {
                        $query->where('jurisdiction', $jurisdiction)
                              ->orWhere('jurisdiction', 'ALL');
                    })
                    ->orderBy('review_priority', 'desc')
                    ->get();
            }
        );
    }
    
    /**
     * Build transaction context
     */
    protected function buildTransactionContext(Transaction $transaction): array
    {
        $account = $transaction->account;
        $user = $account->user;
        
        // Get transaction velocity
        $dailyCount = Transaction::where('account_id', $account->id)
            ->whereDate('created_at', today())
            ->count();
            
        $dailyVolume = Transaction::where('account_id', $account->id)
            ->whereDate('created_at', today())
            ->sum('amount');
        
        return [
            'transaction' => [
                'id' => $transaction->id,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'type' => $transaction->type,
                'created_at' => $transaction->created_at,
            ],
            'account' => [
                'id' => $account->id,
                'type' => $account->type,
                'balance' => $account->getBalance($transaction->currency),
                'age_days' => $account->created_at->diffInDays(now()),
            ],
            'user' => [
                'id' => $user->id,
                'risk_rating' => $user->risk_rating,
                'kyc_level' => $user->kyc_level,
                'pep_status' => $user->pep_status,
                'country' => $user->country,
            ],
            'velocity' => [
                'daily_transaction_count' => $dailyCount,
                'daily_transaction_volume' => $dailyVolume,
            ],
            'metadata' => $transaction->metadata ?? [],
        ];
    }
    
    /**
     * Build customer context
     */
    protected function buildCustomerContext(User $user): array
    {
        $accounts = $user->accounts;
        
        // Calculate aggregate metrics
        $totalBalance = 0;
        $transactionCount30d = 0;
        $transactionVolume30d = 0;
        
        foreach ($accounts as $account) {
            $totalBalance += $account->getBalance();
            
            $accountTransactions = Transaction::where('account_id', $account->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('COUNT(*) as count, SUM(amount) as volume')
                ->first();
                
            $transactionCount30d += $accountTransactions->count ?? 0;
            $transactionVolume30d += $accountTransactions->volume ?? 0;
        }
        
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'country' => $user->country,
                'risk_rating' => $user->risk_rating,
                'kyc_level' => $user->kyc_level,
                'kyc_status' => $user->kyc_status,
                'pep_status' => $user->pep_status,
                'created_at' => $user->created_at,
                'account_age_days' => $user->created_at->diffInDays(now()),
            ],
            'accounts' => [
                'count' => $accounts->count(),
                'total_balance' => $totalBalance,
                'types' => $accounts->pluck('type')->unique()->values(),
            ],
            'activity' => [
                'transaction_count_30d' => $transactionCount30d,
                'transaction_volume_30d' => $transactionVolume30d,
                'avg_transaction_amount' => $transactionCount30d > 0 ? 
                    $transactionVolume30d / $transactionCount30d : 0,
            ],
        ];
    }
    
    /**
     * Build account context
     */
    protected function buildAccountContext(Account $account): array
    {
        $user = $account->user;
        
        // Get account activity metrics
        $monthlyMetrics = Transaction::where('account_id', $account->id)
            ->where('created_at', '>=', now()->subMonth())
            ->selectRaw('
                COUNT(*) as count,
                SUM(amount) as volume,
                MAX(amount) as max_amount,
                AVG(amount) as avg_amount
            ')
            ->first();
        
        return [
            'account' => [
                'id' => $account->id,
                'type' => $account->type,
                'currency' => $account->currency,
                'balance' => $account->getBalance(),
                'created_at' => $account->created_at,
                'age_days' => $account->created_at->diffInDays(now()),
                'status' => $account->status,
            ],
            'user' => [
                'id' => $user->id,
                'risk_rating' => $user->risk_rating,
                'kyc_level' => $user->kyc_level,
                'country' => $user->country,
            ],
            'activity' => [
                'monthly_transaction_count' => $monthlyMetrics->count ?? 0,
                'monthly_transaction_volume' => $monthlyMetrics->volume ?? 0,
                'max_transaction_amount' => $monthlyMetrics->max_amount ?? 0,
                'avg_transaction_amount' => $monthlyMetrics->avg_amount ?? 0,
            ],
        ];
    }
    
    /**
     * Perform aggregation for threshold
     */
    protected function performAggregation(RegulatoryThreshold $threshold, Carbon $date): array
    {
        $aggregateData = [];
        $timePeriodDays = $threshold->getTimePeriodDays();
        $startDate = $date->copy()->subDays($timePeriodDays);
        
        switch ($threshold->aggregation_key) {
            case 'customer':
                $aggregateData = $this->aggregateByCustomer($threshold, $startDate, $date);
                break;
                
            case 'account':
                $aggregateData = $this->aggregateByAccount($threshold, $startDate, $date);
                break;
                
            case 'country':
                $aggregateData = $this->aggregateByCountry($threshold, $startDate, $date);
                break;
                
            case 'merchant':
                $aggregateData = $this->aggregateByMerchant($threshold, $startDate, $date);
                break;
        }
        
        return $aggregateData;
    }
    
    /**
     * Aggregate by customer
     */
    protected function aggregateByCustomer(RegulatoryThreshold $threshold, Carbon $startDate, Carbon $endDate): array
    {
        $aggregateData = [];
        
        $results = DB::table('transactions')
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->join('users', 'accounts.user_id', '=', 'users.id')
            ->whereBetween('transactions.created_at', [$startDate, $endDate])
            ->groupBy('users.id')
            ->select(
                'users.id as user_id',
                DB::raw('COUNT(transactions.id) as transaction_count'),
                DB::raw('SUM(transactions.amount) as total_amount'),
                DB::raw('MAX(transactions.amount) as max_amount')
            )
            ->get();
        
        foreach ($results as $result) {
            $user = User::find($result->user_id);
            if ($user) {
                $aggregateData[$result->user_id] = [
                    'user_id' => $result->user_id,
                    'user_name' => $user->name,
                    'user_risk_rating' => $user->risk_rating,
                    'transaction_count' => $result->transaction_count,
                    'total_amount' => $result->total_amount,
                    'max_amount' => $result->max_amount,
                    'period_start' => $startDate,
                    'period_end' => $endDate,
                ];
            }
        }
        
        return $aggregateData;
    }
    
    /**
     * Aggregate by account
     */
    protected function aggregateByAccount(RegulatoryThreshold $threshold, Carbon $startDate, Carbon $endDate): array
    {
        $aggregateData = [];
        
        $results = DB::table('transactions')
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->whereBetween('transactions.created_at', [$startDate, $endDate])
            ->groupBy('accounts.id')
            ->select(
                'accounts.id as account_id',
                DB::raw('COUNT(transactions.id) as transaction_count'),
                DB::raw('SUM(transactions.amount) as total_amount'),
                DB::raw('COUNT(DISTINCT DATE(transactions.created_at)) as active_days')
            )
            ->get();
        
        foreach ($results as $result) {
            $account = Account::find($result->account_id);
            if ($account) {
                $aggregateData[$result->account_id] = [
                    'account_id' => $result->account_id,
                    'account_type' => $account->type,
                    'user_id' => $account->user_id,
                    'transaction_count' => $result->transaction_count,
                    'total_amount' => $result->total_amount,
                    'active_days' => $result->active_days,
                    'period_start' => $startDate,
                    'period_end' => $endDate,
                ];
            }
        }
        
        return $aggregateData;
    }
    
    /**
     * Aggregate by country
     */
    protected function aggregateByCountry(RegulatoryThreshold $threshold, Carbon $startDate, Carbon $endDate): array
    {
        $aggregateData = [];
        
        $results = DB::table('transactions')
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->join('users', 'accounts.user_id', '=', 'users.id')
            ->whereBetween('transactions.created_at', [$startDate, $endDate])
            ->groupBy('users.country')
            ->select(
                'users.country',
                DB::raw('COUNT(DISTINCT users.id) as user_count'),
                DB::raw('COUNT(transactions.id) as transaction_count'),
                DB::raw('SUM(transactions.amount) as total_amount')
            )
            ->get();
        
        foreach ($results as $result) {
            $aggregateData[$result->country] = [
                'country' => $result->country,
                'user_count' => $result->user_count,
                'transaction_count' => $result->transaction_count,
                'total_amount' => $result->total_amount,
                'period_start' => $startDate,
                'period_end' => $endDate,
            ];
        }
        
        return $aggregateData;
    }
    
    /**
     * Aggregate by merchant
     */
    protected function aggregateByMerchant(RegulatoryThreshold $threshold, Carbon $startDate, Carbon $endDate): array
    {
        // Implement based on merchant data structure
        return [];
    }
    
    /**
     * Handle threshold trigger
     */
    protected function handleThresholdTrigger(RegulatoryThreshold $threshold, $entity, array $context): void
    {
        foreach ($threshold->actions as $action) {
            switch ($action) {
                case RegulatoryThreshold::ACTION_REPORT:
                    $this->createThresholdReport($threshold, $entity, $context);
                    break;
                    
                case RegulatoryThreshold::ACTION_FLAG:
                    $this->flagEntity($entity, $threshold, $context);
                    break;
                    
                case RegulatoryThreshold::ACTION_NOTIFY:
                    $this->sendThresholdNotification($threshold, $entity, $context);
                    break;
                    
                case RegulatoryThreshold::ACTION_BLOCK:
                    if ($entity instanceof Transaction) {
                        $this->blockTransaction($entity, $threshold);
                    }
                    break;
                    
                case RegulatoryThreshold::ACTION_REVIEW:
                    $this->createReviewTask($threshold, $entity, $context);
                    break;
            }
        }
    }
    
    /**
     * Handle aggregate threshold trigger
     */
    protected function handleAggregateThresholdTrigger(
        RegulatoryThreshold $threshold,
        string $aggregateKey,
        array $context
    ): void {
        // Create alert for aggregate threshold breach
        Log::warning('Aggregate threshold triggered', [
            'threshold_code' => $threshold->threshold_code,
            'aggregate_key' => $aggregateKey,
            'context' => $context,
        ]);
        
        // Handle actions
        $this->handleThresholdTrigger($threshold, null, $context);
    }
    
    /**
     * Create threshold report
     */
    protected function createThresholdReport(RegulatoryThreshold $threshold, $entity, array $context): void
    {
        // In production, create actual report
        Log::info('Threshold report created', [
            'threshold_code' => $threshold->threshold_code,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->id ?? null,
        ]);
    }
    
    /**
     * Flag entity
     */
    protected function flagEntity($entity, RegulatoryThreshold $threshold, array $context): void
    {
        if ($entity instanceof User) {
            $entity->update(['risk_rating' => 'high']);
        } elseif ($entity instanceof Account) {
            $entity->update(['flagged' => true]);
        }
    }
    
    /**
     * Send threshold notification
     */
    protected function sendThresholdNotification(RegulatoryThreshold $threshold, $entity, array $context): void
    {
        // In production, send actual notification
        Log::warning('Threshold notification sent', [
            'threshold_code' => $threshold->threshold_code,
            'threshold_name' => $threshold->name,
        ]);
    }
    
    /**
     * Block transaction
     */
    protected function blockTransaction(Transaction $transaction, RegulatoryThreshold $threshold): void
    {
        $transaction->update([
            'status' => 'blocked',
            'metadata' => array_merge($transaction->metadata ?? [], [
                'blocked_by_threshold' => $threshold->threshold_code,
                'blocked_at' => now()->toIso8601String(),
            ]),
        ]);
    }
    
    /**
     * Create review task
     */
    protected function createReviewTask(RegulatoryThreshold $threshold, $entity, array $context): void
    {
        // In production, create task in task management system
        Log::info('Review task created', [
            'threshold_code' => $threshold->threshold_code,
            'priority' => $threshold->review_priority,
        ]);
    }
}