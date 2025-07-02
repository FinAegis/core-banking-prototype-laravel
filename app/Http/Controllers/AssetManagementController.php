<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\TransactionProjection;
use App\Domain\Asset\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AssetManagementController extends Controller
{
    /**
     * Display asset management dashboard
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get all user accounts with balances
        $accounts = $user->accounts()->with(['balances.asset'])->get();
        
        // Calculate portfolio summary
        $portfolio = $this->getPortfolioSummary($accounts);
        
        // Get asset allocation
        $allocation = $this->getAssetAllocation($accounts);
        
        // Get recent transactions
        $recentTransactions = $this->getRecentTransactions($user);
        
        // Get asset performance (mock data for now)
        $performance = $this->getAssetPerformance();
        
        // Get available assets
        $availableAssets = Asset::where('is_active', true)->get();
        
        return view('asset-management.index', compact(
            'accounts',
            'portfolio',
            'allocation',
            'recentTransactions',
            'performance',
            'availableAssets'
        ));
    }
    
    /**
     * Show asset details
     */
    public function show(Asset $asset)
    {
        $user = Auth::user();
        
        // Get user's holdings of this asset
        $holdings = $this->getUserAssetHoldings($user, $asset);
        
        // Get asset statistics
        $statistics = $this->getAssetStatistics($asset);
        
        // Get price history (mock data)
        $priceHistory = $this->getAssetPriceHistory($asset);
        
        // Get related transactions
        $transactions = $this->getAssetTransactions($user, $asset);
        
        return view('asset-management.show', compact(
            'asset',
            'holdings',
            'statistics',
            'priceHistory',
            'transactions'
        ));
    }
    
    /**
     * Show portfolio analytics
     */
    public function analytics(Request $request)
    {
        $user = Auth::user();
        $period = $request->get('period', '30d');
        
        // Get portfolio value over time
        $portfolioHistory = $this->getPortfolioHistory($user, $period);
        
        // Get performance metrics
        $metrics = $this->getPerformanceMetrics($user, $period);
        
        // Get risk analysis
        $riskAnalysis = $this->getRiskAnalysis($user);
        
        // Get diversification score
        $diversification = $this->getDiversificationScore($user);
        
        return view('asset-management.analytics', compact(
            'portfolioHistory',
            'metrics',
            'riskAnalysis',
            'diversification',
            'period'
        ));
    }
    
    /**
     * Export portfolio report
     */
    public function export(Request $request)
    {
        $user = Auth::user();
        $format = $request->get('format', 'csv');
        
        $accounts = $user->accounts()->with(['balances.asset'])->get();
        $portfolio = $this->getPortfolioSummary($accounts);
        
        if ($format === 'csv') {
            return $this->exportCSV($accounts, $portfolio);
        } elseif ($format === 'pdf') {
            return $this->exportPDF($accounts, $portfolio);
        }
        
        return back()->withErrors(['error' => 'Invalid export format']);
    }
    
    /**
     * Get portfolio summary
     */
    private function getPortfolioSummary($accounts)
    {
        $totalValue = 0;
        $totalValueYesterday = 0;
        $assetCount = 0;
        $currencies = [];
        
        foreach ($accounts as $account) {
            foreach ($account->balances as $balance) {
                if ($balance->balance > 0) {
                    $assetCount++;
                    $currencies[$balance->asset->symbol] = true;
                    
                    // Convert to USD for total value calculation
                    $valueInUSD = $this->convertToUSD($balance->balance, $balance->asset->symbol);
                    $totalValue += $valueInUSD;
                    
                    // Mock yesterday's value (95-105% of current)
                    $totalValueYesterday += $valueInUSD * (0.95 + (rand(0, 100) / 1000));
                }
            }
        }
        
        $change = $totalValue - $totalValueYesterday;
        $changePercent = $totalValueYesterday > 0 ? ($change / $totalValueYesterday) * 100 : 0;
        
        return [
            'total_value' => $totalValue,
            'total_value_yesterday' => $totalValueYesterday,
            'change' => $change,
            'change_percent' => $changePercent,
            'asset_count' => $assetCount,
            'currency_count' => count($currencies),
        ];
    }
    
    /**
     * Get asset allocation
     */
    private function getAssetAllocation($accounts)
    {
        $allocation = [];
        $totalValue = 0;
        
        // First pass: calculate total value and individual asset values
        foreach ($accounts as $account) {
            foreach ($account->balances as $balance) {
                if ($balance->balance > 0) {
                    $symbol = $balance->asset->symbol;
                    $valueInUSD = $this->convertToUSD($balance->balance, $symbol);
                    
                    if (!isset($allocation[$symbol])) {
                        $allocation[$symbol] = [
                            'symbol' => $symbol,
                            'name' => $balance->asset->name,
                            'amount' => 0,
                            'value' => 0,
                            'percentage' => 0,
                            'color' => $this->getAssetColor($symbol),
                        ];
                    }
                    
                    $allocation[$symbol]['amount'] += $balance->balance;
                    $allocation[$symbol]['value'] += $valueInUSD;
                    $totalValue += $valueInUSD;
                }
            }
        }
        
        // Second pass: calculate percentages
        foreach ($allocation as &$asset) {
            $asset['percentage'] = $totalValue > 0 ? ($asset['value'] / $totalValue) * 100 : 0;
        }
        
        // Sort by value descending
        usort($allocation, function($a, $b) {
            return $b['value'] <=> $a['value'];
        });
        
        return array_values($allocation);
    }
    
    /**
     * Get recent transactions
     */
    private function getRecentTransactions($user)
    {
        $accountUuids = $user->accounts()->pluck('uuid');
        
        return TransactionProjection::whereIn('account_uuid', $accountUuids)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->uuid,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'description' => $transaction->description,
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at,
                ];
            });
    }
    
    /**
     * Get asset performance data
     */
    private function getAssetPerformance()
    {
        // Mock performance data
        $assets = ['USD', 'EUR', 'GBP', 'PHP', 'GCU'];
        $performance = [];
        
        foreach ($assets as $asset) {
            $change = rand(-500, 500) / 100; // -5% to +5%
            $performance[] = [
                'symbol' => $asset,
                'change_24h' => $change,
                'change_7d' => $change * 2.5,
                'change_30d' => $change * 5,
                'price' => $this->getMockPrice($asset),
            ];
        }
        
        return $performance;
    }
    
    /**
     * Get user's holdings of specific asset
     */
    private function getUserAssetHoldings($user, $asset)
    {
        $holdings = [];
        $accounts = $user->accounts()->with(['balances' => function($query) use ($asset) {
            $query->where('asset_code', $asset->symbol);
        }])->get();
        
        foreach ($accounts as $account) {
            foreach ($account->balances as $balance) {
                if ($balance->balance > 0) {
                    $holdings[] = [
                        'account' => $account,
                        'balance' => $balance,
                        'value_usd' => $this->convertToUSD($balance->balance, $asset->symbol),
                    ];
                }
            }
        }
        
        return $holdings;
    }
    
    /**
     * Get asset statistics
     */
    private function getAssetStatistics($asset)
    {
        return Cache::remember("asset_stats_{$asset->symbol}", 300, function() use ($asset) {
            return [
                'total_supply' => $asset->symbol === 'GCU' ? 1000000000 : null,
                'market_cap' => $this->getMockMarketCap($asset->symbol),
                'holders' => DB::table('account_balances')
                    ->where('asset_code', $asset->symbol)
                    ->where('balance', '>', 0)
                    ->count(),
                'transactions_24h' => rand(100, 1000),
                'volume_24h' => rand(10000, 100000) * 100, // In cents
            ];
        });
    }
    
    /**
     * Get asset price history
     */
    private function getAssetPriceHistory($asset)
    {
        $history = [];
        $basePrice = $this->getMockPrice($asset->symbol);
        
        // Generate 30 days of price history
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $variation = (100 - rand(0, 10)) / 100; // 90-100% of base price
            
            $history[] = [
                'date' => $date->format('Y-m-d'),
                'price' => $basePrice * $variation,
                'volume' => rand(5000, 50000) * 100,
            ];
        }
        
        return $history;
    }
    
    /**
     * Get asset-specific transactions
     */
    private function getAssetTransactions($user, $asset)
    {
        $accountUuids = $user->accounts()->pluck('uuid');
        
        return TransactionProjection::whereIn('account_uuid', $accountUuids)
            ->where('currency', $asset->symbol)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }
    
    /**
     * Convert amount to USD
     */
    private function convertToUSD($amount, $currency)
    {
        // Simple mock conversion rates
        $rates = [
            'USD' => 1,
            'EUR' => 1.09,
            'GBP' => 1.27,
            'PHP' => 0.018,
            'GCU' => 0.1, // Mock GCU value
        ];
        
        $rate = $rates[$currency] ?? 1;
        return $amount * $rate;
    }
    
    /**
     * Get asset color for charts
     */
    private function getAssetColor($symbol)
    {
        $colors = [
            'USD' => '#22c55e',
            'EUR' => '#3b82f6',
            'GBP' => '#8b5cf6',
            'PHP' => '#f59e0b',
            'GCU' => '#ec4899',
        ];
        
        return $colors[$symbol] ?? '#6b7280';
    }
    
    /**
     * Get mock price for asset
     */
    private function getMockPrice($symbol)
    {
        $prices = [
            'USD' => 100, // 1 USD = 100 cents
            'EUR' => 109,
            'GBP' => 127,
            'PHP' => 1.8,
            'GCU' => 10,
        ];
        
        return $prices[$symbol] ?? 100;
    }
    
    /**
     * Get mock market cap
     */
    private function getMockMarketCap($symbol)
    {
        $caps = [
            'USD' => 1000000000000, // $10 billion in cents
            'EUR' => 800000000000,
            'GBP' => 600000000000,
            'PHP' => 100000000000,
            'GCU' => 10000000000, // $100 million
        ];
        
        return $caps[$symbol] ?? 0;
    }
    
    /**
     * Get portfolio history
     */
    private function getPortfolioHistory($user, $period)
    {
        $days = match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            default => 30,
        };
        
        $history = [];
        $baseValue = 100000; // $1000 in cents
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $variation = 1 + ((rand(-10, 10) / 100)); // ±10% variation
            $baseValue = $baseValue * $variation;
            
            $history[] = [
                'date' => $date->format('Y-m-d'),
                'value' => round($baseValue),
            ];
        }
        
        return $history;
    }
    
    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics($user, $period)
    {
        return [
            'total_return' => rand(5, 20),
            'annualized_return' => rand(10, 30),
            'best_performer' => 'GCU',
            'worst_performer' => 'PHP',
            'volatility' => rand(10, 25),
            'sharpe_ratio' => rand(50, 150) / 100,
        ];
    }
    
    /**
     * Get risk analysis
     */
    private function getRiskAnalysis($user)
    {
        return [
            'risk_score' => rand(30, 70),
            'risk_level' => 'Moderate',
            'var_95' => rand(500, 1500), // Value at Risk
            'max_drawdown' => rand(5, 15),
            'recommendations' => [
                'Consider diversifying into more stable assets',
                'Your GCU allocation is within recommended limits',
                'Consider rebalancing quarterly',
            ],
        ];
    }
    
    /**
     * Get diversification score
     */
    private function getDiversificationScore($user)
    {
        $accounts = $user->accounts()->with(['balances.asset'])->get();
        $allocation = $this->getAssetAllocation($accounts);
        
        // Simple diversification score based on number of assets and allocation
        $assetCount = count($allocation);
        $maxAllocation = max(array_column($allocation, 'percentage'));
        
        $score = min(100, ($assetCount * 10) + (100 - $maxAllocation));
        
        return [
            'score' => $score,
            'rating' => $score >= 70 ? 'Good' : ($score >= 40 ? 'Fair' : 'Poor'),
            'suggestion' => $score < 70 ? 'Consider spreading investments across more assets' : 'Well diversified portfolio',
        ];
    }
    
    /**
     * Export portfolio as CSV
     */
    private function exportCSV($accounts, $portfolio)
    {
        $filename = "portfolio_" . now()->format('Y-m-d') . ".csv";
        
        return response()->streamDownload(function() use ($accounts) {
            $handle = fopen('php://output', 'w');
            
            // Headers
            fputcsv($handle, [
                'Account',
                'Asset',
                'Balance',
                'Value (USD)',
                'Percentage',
            ]);
            
            // Data
            $totalValue = 0;
            $rows = [];
            
            foreach ($accounts as $account) {
                foreach ($account->balances as $balance) {
                    if ($balance->balance > 0) {
                        $valueUSD = $this->convertToUSD($balance->balance, $balance->asset->symbol);
                        $totalValue += $valueUSD;
                        
                        $rows[] = [
                            'account' => $account->name,
                            'asset' => $balance->asset->symbol,
                            'balance' => $balance->balance / 100,
                            'value_usd' => $valueUSD / 100,
                        ];
                    }
                }
            }
            
            // Write rows with percentages
            foreach ($rows as $row) {
                $percentage = $totalValue > 0 ? ($row['value_usd'] * 100 / ($totalValue / 100)) : 0;
                
                fputcsv($handle, [
                    $row['account'],
                    $row['asset'],
                    number_format($row['balance'], 2),
                    number_format($row['value_usd'], 2),
                    number_format($percentage, 2) . '%',
                ]);
            }
            
            // Summary
            fputcsv($handle, []);
            fputcsv($handle, ['Total Portfolio Value (USD)', '', '', number_format($totalValue / 100, 2), '100.00%']);
            
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
    
    /**
     * Export portfolio as PDF
     */
    private function exportPDF($accounts, $portfolio)
    {
        // For now, redirect to CSV export
        // In production, would use a PDF library like DomPDF or TCPDF
        return $this->exportCSV($accounts, $portfolio);
    }
}