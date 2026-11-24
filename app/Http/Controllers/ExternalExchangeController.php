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
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'External Exchange Management',
    description: 'External exchange connections, arbitrage, and price alignment'
)]
class ExternalExchangeController extends Controller
{
    public function __construct(
        private ExternalExchangeServiceInterface $externalExchangeService,
        private ArbitrageServiceInterface $arbitrageService,
        private PriceAggregatorInterface $priceAggregator,
        private OrderService $orderService
    ) {
    }

        #[OA\Get(
            path: '/external-exchanges',
            operationId: 'externalExchangeManagementIndex',
            tags: ['External Exchange Management'],
            summary: 'External exchanges dashboard',
            description: 'Returns the external exchanges management dashboard',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
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

        #[OA\Post(
            path: '/external-exchanges/connect',
            operationId: 'externalExchangeManagementConnect',
            tags: ['External Exchange Management'],
            summary: 'Connect external exchange',
            description: 'Connects to an external exchange',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
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

        #[OA\Post(
            path: '/external-exchanges/{id}/disconnect',
            operationId: 'externalExchangeManagementDisconnect',
            tags: ['External Exchange Management'],
            summary: 'Disconnect external exchange',
            description: 'Disconnects from an external exchange',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
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
}
