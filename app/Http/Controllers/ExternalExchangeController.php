<?php

namespace App\Http\Controllers;

use App\Domain\Exchange\Contracts\ArbitrageServiceInterface;
use App\Domain\Exchange\Contracts\ExternalExchangeServiceInterface;
use App\Domain\Exchange\Contracts\PriceAggregatorInterface;
use App\Domain\Exchange\Services\OrderService;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExternalExchangeController extends Controller
{
    public function __construct(
        private ExternalExchangeServiceInterface $externalExchangeService,
        private ArbitrageServiceInterface $arbitrageService,
        private PriceAggregatorInterface $priceAggregator,
        private OrderService $orderService
    ) {
    }

    /**
     * Display external exchange dashboard.
     */
    public function index()
    {
        // Get connected exchanges
        $connectedExchanges = $this->getConnectedExchanges();

        // Get price comparisons across exchanges
        $priceComparisons = $this->getPriceComparisons();

        // Get recent arbitrage opportunities
        $arbitrageOpportunities = collect($this->arbitrageService->findOpportunities('BTC/USD'));

        // Get user's external exchange balances
        $externalBalances = $this->getExternalBalances();

        // Get recent external trades
        $recentTrades = $this->getRecentExternalTrades();

        return view(
            'exchange.external.index',
            compact(
                'connectedExchanges',
                'priceComparisons',
                'arbitrageOpportunities',
                'externalBalances',
                'recentTrades'
            )
        );
    }

    /**
     * Show arbitrage opportunities.
     */
    public function arbitrage()
    {
        // Get current arbitrage opportunities
        $opportunities = $this->arbitrageService->findOpportunities();

        // Get historical arbitrage performance
        $historicalPerformance = $this->getHistoricalArbitragePerformance();

        // Get active arbitrage bots/strategies
        $activeStrategies = $this->getActiveArbitrageStrategies();

        // Get supported trading pairs for arbitrage
        $supportedPairs = $this->getSupportedArbitragePairs();

        return view(
            'exchange.external.arbitrage',
            compact(
                'opportunities',
                'historicalPerformance',
                'activeStrategies',
                'supportedPairs'
            )
        );
    }

    /**
     * Execute arbitrage opportunity.
     */
    public function executeArbitrage(Request $request)
    {
        $validated = $request->validate(
            [
                'opportunity_id'     => 'required|string',
                'amount'             => 'required|numeric|min:0.00000001',
                'slippage_tolerance' => 'required|numeric|min:0|max:5',
                'password'           => 'required|string',
            ]
        );

        try {
            // Execute the arbitrage trade
            $result = $this->arbitrageService->executeArbitrage(
                $validated['opportunity_id'],
                $validated['amount'],
                [
                    'user_uuid'          => Auth::user()->uuid,
                    'slippage_tolerance' => $validated['slippage_tolerance'],
                ]
            );

            return redirect()
                ->route('exchange.external.arbitrage')
                ->with('success', 'Arbitrage trade executed successfully. Profit: ' . $result['profit']);
        } catch (Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to execute arbitrage: ' . $e->getMessage()]);
        }
    }

    /**
     * Show price alignment dashboard.
     */
    public function priceAlignment()
    {
        // Get price discrepancies across exchanges
        $priceDiscrepancies = $this->priceAggregator->getPriceDiscrepancies();

        // Get our exchange prices vs market
        $ourPrices = $this->getOurExchangePrices();

        // Get recommended price adjustments
        $recommendedAdjustments = $this->getRecommendedPriceAdjustments();

        // Get price alignment history
        $alignmentHistory = $this->getPriceAlignmentHistory();

        return view(
            'exchange.external.price-alignment',
            compact(
                'priceDiscrepancies',
                'ourPrices',
                'recommendedAdjustments',
                'alignmentHistory'
            )
        );
    }

    /**
     * Update price alignment settings.
     */
    public function updatePriceAlignment(Request $request)
    {
        $validated = $request->validate(
            [
                'auto_align'       => 'boolean',
                'max_spread'       => 'required|numeric|min:0|max:10',
                'update_frequency' => 'required|integer|min:1|max:3600',
                'exchanges'        => 'required|array',
                'exchanges.*'      => 'string|in:binance,kraken,coinbase',
            ]
        );

        try {
            // Update price alignment settings
            $this->priceAggregator->updateAlignmentSettings(
                [
                    'auto_align'          => $validated['auto_align'] ?? false,
                    'max_spread'          => $validated['max_spread'],
                    'update_frequency'    => $validated['update_frequency'],
                    'reference_exchanges' => $validated['exchanges'],
                ]
            );

            return redirect()
                ->route('exchange.external.price-alignment')
                ->with('success', 'Price alignment settings updated successfully');
        } catch (Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update settings: ' . $e->getMessage()]);
        }
    }

    /**
     * Connect external exchange.
     */
    public function connect(Request $request)
    {
        $validated = $request->validate(
            [
                'exchange'   => 'required|string|in:binance,kraken,coinbase',
                'api_key'    => 'required|string',
                'api_secret' => 'required|string',
                'testnet'    => 'boolean',
            ]
        );

        try {
            // Connect to external exchange
            $connection = $this->externalExchangeService->connectExchange(
                Auth::user()->uuid,
                $validated['exchange'],
                [
                    'api_key'    => $validated['api_key'],
                    'api_secret' => $validated['api_secret'],
                    'testnet'    => $validated['testnet'] ?? false,
                ]
            );

            return redirect()
                ->route('exchange.external.index')
                ->with('success', 'Successfully connected to ' . ucfirst($validated['exchange']));
        } catch (Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to connect: ' . $e->getMessage()]);
        }
    }

    /**
     * Disconnect external exchange.
     */
    public function disconnect($exchange)
    {
        try {
            $this->externalExchangeService->disconnectExchange(
                Auth::user()->uuid,
                $exchange
            );

            return redirect()
                ->route('exchange.external.index')
                ->with('success', 'Successfully disconnected from ' . ucfirst($exchange));
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Failed to disconnect: ' . $e->getMessage()]);
        }
    }

    /**
     * Get connected exchanges.
     */
    private function getConnectedExchanges()
    {
        try {
            return DB::table('external_exchange_connections')
                ->where('user_uuid', Auth::user()->uuid)
                ->where('is_active', true)
                ->get()
                ->map(
                    function ($connection) {
                        return [
                            'exchange'     => $connection->exchange,
                            'connected_at' => $connection->created_at,
                            'testnet'      => $connection->testnet,
                            'last_sync'    => $connection->last_sync_at,
                            'status'       => $connection->status,
                        ];
                    }
                );
        } catch (Exception $e) {
            // Return empty collection if table doesn't exist
            return collect();
        }
    }

    /**
     * Get price comparisons.
     */
    private function getPriceComparisons()
    {
        $pairs = ['BTC/USD', 'ETH/USD', 'GCU/USD'];
        $comparisons = [];

        foreach ($pairs as $pair) {
            $prices = $this->priceAggregator->getPricesAcrossExchanges($pair);
            $comparisons[$pair] = [
                'internal' => $prices['internal'] ?? null,
                'binance'  => $prices['binance'] ?? null,
                'kraken'   => $prices['kraken'] ?? null,
                'coinbase' => $prices['coinbase'] ?? null,
                'average'  => $prices['average'] ?? null,
                'spread'   => $prices['spread'] ?? null,
            ];
        }

        return collect($comparisons);
    }

    /**
     * Get external balances.
     */
    private function getExternalBalances()
    {
        $balances = [];
        $connections = $this->getConnectedExchanges();

        foreach ($connections as $connection) {
            try {
                $exchangeBalances = $this->externalExchangeService->getBalances(
                    Auth::user()->uuid,
                    $connection['exchange']
                );
                $balances[$connection['exchange']] = $exchangeBalances;
            } catch (Exception $e) {
                $balances[$connection['exchange']] = ['error' => true];
            }
        }

        return collect($balances);
    }

    /**
     * Get recent external trades.
     */
    private function getRecentExternalTrades()
    {
        try {
            return DB::table('external_exchange_trades')
                ->where('user_uuid', Auth::user()->uuid)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        } catch (Exception $e) {
            return collect([]);
        }
    }

    /**
     * Get historical arbitrage performance.
     */
    private function getHistoricalArbitragePerformance()
    {
        $thirtyDaysAgo = now()->subDays(30);

        return DB::table('arbitrage_trades')
            ->where('user_uuid', Auth::user()->uuid)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as trades'),
                DB::raw('SUM(profit_usd) as total_profit'),
                DB::raw('AVG(profit_percentage) as avg_profit_percentage')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Get active arbitrage strategies.
     */
    private function getActiveArbitrageStrategies()
    {
        return DB::table('arbitrage_strategies')
            ->where('user_uuid', Auth::user()->uuid)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get supported arbitrage pairs.
     */
    private function getSupportedArbitragePairs()
    {
        return [
            'BTC/USD'   => ['binance', 'kraken', 'coinbase'],
            'ETH/USD'   => ['binance', 'kraken', 'coinbase'],
            'BTC/ETH'   => ['binance', 'kraken'],
            'MATIC/USD' => ['binance', 'coinbase'],
            'BNB/USD'   => ['binance'],
        ];
    }

    /**
     * Get our exchange prices.
     */
    private function getOurExchangePrices()
    {
        return DB::table('exchange_prices')
            ->where('exchange', 'internal')
            ->whereIn('pair', ['BTC/USD', 'ETH/USD', 'GCU/USD'])
            ->get()
            ->keyBy('pair');
    }

    /**
     * Get recommended price adjustments.
     */
    private function getRecommendedPriceAdjustments()
    {
        $recommendations = [];
        $comparisons = $this->getPriceComparisons();

        foreach ($comparisons as $pair => $prices) {
            if ($prices['internal'] && $prices['average']) {
                $deviation = abs($prices['internal'] - $prices['average']) / $prices['average'] * 100;

                if ($deviation > 1) { // More than 1% deviation
                    $recommendations[] = [
                        'pair'                 => $pair,
                        'current_price'        => $prices['internal'],
                        'market_average'       => $prices['average'],
                        'recommended_price'    => $prices['average'],
                        'deviation_percentage' => $deviation,
                        'action'               => $prices['internal'] > $prices['average'] ? 'decrease' : 'increase',
                    ];
                }
            }
        }

        return $recommendations;
    }

    /**
     * Get price alignment history.
     */
    private function getPriceAlignmentHistory()
    {
        return DB::table('price_alignment_history')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }
}
