<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Account;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Banking\Models\BankTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FundFlowController extends Controller
{
    /**
     * Display the fund flow visualization page
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $accounts = $user->accounts()->with('balances.asset')->get();
        
        // Get filter parameters
        $filters = [
            'period' => $request->get('period', '7days'),
            'account' => $request->get('account', 'all'),
            'flow_type' => $request->get('flow_type', 'all'),
        ];
        
        // Get date range based on period
        $dateRange = $this->getDateRange($filters['period']);
        
        // Get fund flow data
        $flowData = $this->getFundFlowData($user, $dateRange, $filters);
        
        // Get flow statistics
        $statistics = $this->getFlowStatistics($user, $dateRange, $filters);
        
        // Get account network for visualization
        $networkData = $this->getAccountNetwork($user, $dateRange, $filters);
        
        // Get daily flow chart data
        $chartData = $this->getDailyFlowData($user, $dateRange, $filters);
        
        return Inertia::render('FundFlow/Visualization', [
            'accounts' => $accounts,
            'flowData' => $flowData,
            'statistics' => $statistics,
            'networkData' => $networkData,
            'chartData' => $chartData,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get fund flow details for a specific account
     */
    public function accountFlow($accountUuid)
    {
        $user = Auth::user();
        $account = $user->accounts()->where('uuid', $accountUuid)->firstOrFail();
        
        // Get all flows for this account
        $inflows = $this->getAccountInflows($account);
        $outflows = $this->getAccountOutflows($account);
        
        // Calculate flow balance
        $flowBalance = $this->calculateFlowBalance($inflows, $outflows);
        
        // Get counterparty analysis
        $counterparties = $this->getCounterpartyAnalysis($account);
        
        return Inertia::render('FundFlow/AccountDetail', [
            'account' => $account->load('balances.asset'),
            'inflows' => $inflows,
            'outflows' => $outflows,
            'flowBalance' => $flowBalance,
            'counterparties' => $counterparties,
        ]);
    }
    
    /**
     * Get fund flow data for API/export
     */
    public function data(Request $request)
    {
        $user = Auth::user();
        
        $filters = [
            'period' => $request->get('period', '7days'),
            'account' => $request->get('account', 'all'),
            'flow_type' => $request->get('flow_type', 'all'),
        ];
        
        $dateRange = $this->getDateRange($filters['period']);
        $flowData = $this->getFundFlowData($user, $dateRange, $filters);
        
        return response()->json([
            'flows' => $flowData,
            'period' => $filters['period'],
            'generated_at' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Get date range based on period
     */
    private function getDateRange($period)
    {
        $end = now();
        
        switch ($period) {
            case '24hours':
                $start = now()->subDay();
                break;
            case '7days':
                $start = now()->subDays(7);
                break;
            case '30days':
                $start = now()->subDays(30);
                break;
            case '90days':
                $start = now()->subDays(90);
                break;
            case 'custom':
                // Handle custom date range from request
                $start = request()->get('start_date', now()->subDays(7));
                $end = request()->get('end_date', now());
                break;
            default:
                $start = now()->subDays(7);
        }
        
        return [
            'start' => $start,
            'end' => $end,
        ];
    }
    
    /**
     * Get fund flow data for visualization
     */
    private function getFundFlowData($user, $dateRange, $filters)
    {
        $flows = [];
        
        // Get all transactions for user's accounts
        $transactions = DB::table('transaction_projections')
            ->join('accounts', 'transaction_projections.account_uuid', '=', 'accounts.uuid')
            ->where('accounts.user_uuid', $user->uuid)
            ->whereBetween('transaction_projections.created_at', [$dateRange['start'], $dateRange['end']])
            ->where('transaction_projections.status', 'completed');
        
        // Apply filters
        if ($filters['account'] !== 'all') {
            $transactions->where('transaction_projections.account_uuid', $filters['account']);
        }
        
        if ($filters['flow_type'] !== 'all') {
            $transactions->where('transaction_projections.type', $filters['flow_type']);
        }
        
        $transactionData = $transactions->select(
            'transaction_projections.*',
            'accounts.name as account_name',
            'accounts.uuid as account_uuid'
        )->get();
        
        // Process transactions into flows
        foreach ($transactionData as $transaction) {
            $flow = [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'from' => $this->getFlowSource($transaction),
                'to' => $this->getFlowDestination($transaction),
                'timestamp' => $transaction->created_at,
                'status' => $transaction->status,
                'description' => $this->getFlowDescription($transaction),
            ];
            
            $flows[] = $flow;
        }
        
        // Get inter-account transfers
        $transfers = $this->getInterAccountTransfers($user, $dateRange, $filters);
        $flows = array_merge($flows, $transfers);
        
        // Sort by timestamp
        usort($flows, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return $flows;
    }
    
    /**
     * Get flow statistics
     */
    private function getFlowStatistics($user, $dateRange, $filters)
    {
        $stats = DB::table('transactions')
            ->join('accounts', 'transaction_projections.account_uuid', '=', 'accounts.uuid')
            ->where('accounts.user_uuid', $user->uuid)
            ->whereBetween('transaction_projections.created_at', [$dateRange['start'], $dateRange['end']])
            ->where('transaction_projections.status', 'completed')
            ->select(
                DB::raw('SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as total_inflow'),
                DB::raw('SUM(CASE WHEN type = "withdrawal" THEN amount ELSE 0 END) as total_outflow'),
                DB::raw('SUM(CASE WHEN type = "transfer" AND amount > 0 THEN amount ELSE 0 END) as total_internal'),
                DB::raw('COUNT(DISTINCT CASE WHEN type = "deposit" THEN DATE(created_at) END) as active_days'),
                DB::raw('COUNT(*) as total_flows')
            );
        
        // Apply filters
        if ($filters['account'] !== 'all') {
            $stats->where('transaction_projections.account_uuid', $filters['account']);
        }
        
        $result = $stats->first();
        
        // Calculate net flow
        $result->net_flow = $result->total_inflow - $result->total_outflow;
        
        // Calculate average daily flow
        $days = $dateRange['start']->diffInDays($dateRange['end']) ?: 1;
        $result->avg_daily_inflow = round($result->total_inflow / $days);
        $result->avg_daily_outflow = round($result->total_outflow / $days);
        
        // Get top flow categories
        $result->top_categories = $this->getTopFlowCategories($user, $dateRange, $filters);
        
        return $result;
    }
    
    /**
     * Get account network data for visualization
     */
    private function getAccountNetwork($user, $dateRange, $filters)
    {
        $nodes = [];
        $edges = [];
        
        // Add user's accounts as nodes
        $accounts = $user->accounts()->get();
        foreach ($accounts as $account) {
            $nodes[] = [
                'id' => 'account_' . $account->uuid,
                'label' => $account->name,
                'type' => 'account',
                'group' => 'internal',
                'balance' => $account->balances->sum('balance'),
            ];
        }
        
        // Add external entities as nodes and create edges
        $externalNodes = [];
        $flowData = $this->getFundFlowData($user, $dateRange, $filters);
        
        foreach ($flowData as $flow) {
            // Create nodes for external entities
            if ($flow['from']['type'] === 'external' && !isset($externalNodes[$flow['from']['id']])) {
                $nodes[] = [
                    'id' => $flow['from']['id'],
                    'label' => $flow['from']['name'],
                    'type' => 'external',
                    'group' => $flow['from']['group'] ?? 'bank',
                ];
                $externalNodes[$flow['from']['id']] = true;
            }
            
            if ($flow['to']['type'] === 'external' && !isset($externalNodes[$flow['to']['id']])) {
                $nodes[] = [
                    'id' => $flow['to']['id'],
                    'label' => $flow['to']['name'],
                    'type' => 'external',
                    'group' => $flow['to']['group'] ?? 'bank',
                ];
                $externalNodes[$flow['to']['id']] = true;
            }
            
            // Create edge
            $edges[] = [
                'id' => 'edge_' . $flow['id'],
                'source' => $flow['from']['id'],
                'target' => $flow['to']['id'],
                'value' => $flow['amount'],
                'currency' => $flow['currency'],
                'type' => $flow['type'],
                'timestamp' => $flow['timestamp'],
            ];
        }
        
        // Aggregate edges between same nodes
        $aggregatedEdges = $this->aggregateEdges($edges);
        
        return [
            'nodes' => $nodes,
            'edges' => $aggregatedEdges,
        ];
    }
    
    /**
     * Get daily flow data for chart
     */
    private function getDailyFlowData($user, $dateRange, $filters)
    {
        $query = DB::table('transactions')
            ->join('accounts', 'transaction_projections.account_uuid', '=', 'accounts.uuid')
            ->where('accounts.user_uuid', $user->uuid)
            ->whereBetween('transaction_projections.created_at', [$dateRange['start'], $dateRange['end']])
            ->where('transaction_projections.status', 'completed');
        
        // Apply filters
        if ($filters['account'] !== 'all') {
            $query->where('transaction_projections.account_uuid', $filters['account']);
        }
        
        $dailyData = $query->select(
                DB::raw('DATE(transaction_projections.created_at) as date'),
                DB::raw('SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as inflow'),
                DB::raw('SUM(CASE WHEN type = "withdrawal" THEN amount ELSE 0 END) as outflow'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Fill in missing dates
        $chartData = [];
        $currentDate = $dateRange['start']->copy();
        
        while ($currentDate <= $dateRange['end']) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayData = $dailyData->firstWhere('date', $dateStr);
            
            $chartData[] = [
                'date' => $dateStr,
                'inflow' => $dayData ? $dayData->inflow : 0,
                'outflow' => $dayData ? $dayData->outflow : 0,
                'net' => $dayData ? ($dayData->inflow - $dayData->outflow) : 0,
                'transactions' => $dayData ? $dayData->transaction_count : 0,
            ];
            
            $currentDate->addDay();
        }
        
        return $chartData;
    }
    
    /**
     * Get account inflows
     */
    private function getAccountInflows($account)
    {
        return DB::table('transaction_projections')
            ->where('account_uuid', $account->uuid)
            ->where('type', 'deposit')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
    }
    
    /**
     * Get account outflows
     */
    private function getAccountOutflows($account)
    {
        return DB::table('transaction_projections')
            ->where('account_uuid', $account->uuid)
            ->whereIn('type', ['withdrawal', 'transfer'])
            ->where('amount', '<', 0)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
    }
    
    /**
     * Calculate flow balance
     */
    private function calculateFlowBalance($inflows, $outflows)
    {
        $totalInflow = $inflows->sum('amount');
        $totalOutflow = abs($outflows->sum('amount'));
        
        return [
            'total_inflow' => $totalInflow,
            'total_outflow' => $totalOutflow,
            'net_flow' => $totalInflow - $totalOutflow,
            'flow_ratio' => $totalOutflow > 0 ? round($totalInflow / $totalOutflow, 2) : null,
        ];
    }
    
    /**
     * Get counterparty analysis
     */
    private function getCounterpartyAnalysis($account)
    {
        // This would analyze transaction metadata to identify frequent counterparties
        // For now, return mock data
        return [
            'top_sources' => [],
            'top_destinations' => [],
            'frequent_patterns' => [],
        ];
    }
    
    /**
     * Get flow source
     */
    private function getFlowSource($transaction)
    {
        if ($transaction->type === 'deposit') {
            // External source
            return [
                'id' => 'external_' . ($transaction->source_id ?? 'bank'),
                'name' => $transaction->source_name ?? 'External Bank',
                'type' => 'external',
                'group' => 'bank',
            ];
        } else {
            // Internal account
            return [
                'id' => 'account_' . $transaction->account_uuid,
                'name' => $transaction->account_name,
                'type' => 'account',
                'group' => 'internal',
            ];
        }
    }
    
    /**
     * Get flow destination
     */
    private function getFlowDestination($transaction)
    {
        if ($transaction->type === 'withdrawal') {
            // External destination
            return [
                'id' => 'external_' . ($transaction->destination_id ?? 'bank'),
                'name' => $transaction->destination_name ?? 'External Bank',
                'type' => 'external',
                'group' => 'bank',
            ];
        } else {
            // Internal account
            return [
                'id' => 'account_' . $transaction->account_uuid,
                'name' => $transaction->account_name,
                'type' => 'account',
                'group' => 'internal',
            ];
        }
    }
    
    /**
     * Get flow description
     */
    private function getFlowDescription($transaction)
    {
        $descriptions = [
            'deposit' => 'Deposit from external source',
            'withdrawal' => 'Withdrawal to external destination',
            'transfer' => 'Internal transfer',
            'exchange' => 'Currency exchange',
        ];
        
        return $descriptions[$transaction->type] ?? $transaction->type;
    }
    
    /**
     * Get inter-account transfers
     */
    private function getInterAccountTransfers($user, $dateRange, $filters)
    {
        // Get transfers between user's accounts
        // This would need to be implemented based on your transfer tracking
        return [];
    }
    
    /**
     * Get top flow categories
     */
    private function getTopFlowCategories($user, $dateRange, $filters)
    {
        return DB::table('transaction_projections')
            ->join('accounts', 'transaction_projections.account_uuid', '=', 'accounts.uuid')
            ->where('accounts.user_uuid', $user->uuid)
            ->whereBetween('transaction_projections.created_at', [$dateRange['start'], $dateRange['end']])
            ->where('transaction_projections.status', 'completed')
            ->select(
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(ABS(amount)) as total_amount')
            )
            ->groupBy('type')
            ->orderBy('total_amount', 'desc')
            ->limit(5)
            ->get();
    }
    
    /**
     * Aggregate edges between same nodes
     */
    private function aggregateEdges($edges)
    {
        $aggregated = [];
        
        foreach ($edges as $edge) {
            $key = $edge['source'] . '_' . $edge['target'] . '_' . $edge['currency'];
            
            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'id' => 'agg_' . md5($key),
                    'source' => $edge['source'],
                    'target' => $edge['target'],
                    'value' => 0,
                    'currency' => $edge['currency'],
                    'count' => 0,
                    'types' => [],
                ];
            }
            
            $aggregated[$key]['value'] += $edge['value'];
            $aggregated[$key]['count']++;
            $aggregated[$key]['types'][] = $edge['type'];
        }
        
        return array_values($aggregated);
    }
}