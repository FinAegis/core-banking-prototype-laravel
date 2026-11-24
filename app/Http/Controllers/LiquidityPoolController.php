<?php

namespace App\Http\Controllers;

use App\Domain\Exchange\Contracts\ExchangeServiceInterface;
use App\Domain\Exchange\Contracts\LiquidityPoolServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Liquidity Pools',
    description: 'Liquidity pool pages of the demo trading engine'
)]
class LiquidityPoolController extends Controller
{
    public function __construct(
        private LiquidityPoolServiceInterface $liquidityPoolService,
        private ExchangeServiceInterface $exchangeService
    ) {
    }

        #[OA\Get(
            path: '/pools',
            operationId: 'liquidityPoolsIndex',
            tags: ['Liquidity Pools'],
            summary: 'List liquidity pools',
            description: 'Returns the liquidity pools overview page',
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
        $pools = $this->liquidityPoolService->getAllPools();
        $userLiquidity = $this->getUserLiquidityPositions();
        $marketData = $this->getMarketData();

        return view('liquidity.index', compact('pools', 'userLiquidity', 'marketData'));
    }

        #[OA\Get(
            path: '/pools/{id}',
            operationId: 'liquidityPoolsShow',
            tags: ['Liquidity Pools'],
            summary: 'Show pool details',
            description: 'Returns details of a specific liquidity pool',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function show($poolId)
    {
        $poolProjection = $this->liquidityPoolService->getPool($poolId);

        if (! $poolProjection) {
            abort(404, 'Pool not found');
        }

        $pool = [
            'id'              => $poolProjection->pool_id,
            'base_currency'   => $poolProjection->base_currency,
            'quote_currency'  => $poolProjection->quote_currency,
            'fee_rate'        => $poolProjection->fee_rate,
            'base_reserve'    => $poolProjection->base_reserve,
            'quote_reserve'   => $poolProjection->quote_reserve,
            'total_liquidity' => $poolProjection->total_liquidity,
            'is_active'       => $poolProjection->is_active,
            'created_at'      => $poolProjection->created_at,
        ];

        $metrics = $this->liquidityPoolService->getPoolMetrics($poolId);
        $userPosition = $this->getUserPositionInPool($poolId);
        $priceHistory = $this->getPoolPriceHistory($poolId);

        return view('liquidity.show', compact('pool', 'metrics', 'userPosition', 'priceHistory'));
    }

    /**
     * Get user's liquidity positions.
     */
    private function getUserLiquidityPositions()
    {
        $user = Auth::user();
        /** @var User $user */
        if (! $user) {
            return collect();
        }

        // Mock data for now - in production, fetch from database
        return collect(
            [
                [
                    'pool_id'          => 'btc-usdt',
                    'base_currency'    => 'BTC',
                    'quote_currency'   => 'USDT',
                    'liquidity_tokens' => 1000,
                    'share_percentage' => 0.05,
                    'value_usd'        => 5000,
                    'pnl'              => 250,
                    'pnl_percentage'   => 5.0,
                ],
                [
                    'pool_id'          => 'eth-usdt',
                    'base_currency'    => 'ETH',
                    'quote_currency'   => 'USDT',
                    'liquidity_tokens' => 2000,
                    'share_percentage' => 0.1,
                    'value_usd'        => 8000,
                    'pnl'              => -100,
                    'pnl_percentage'   => -1.25,
                ],
            ]
        );
    }

    /**
     * Get market data for pools.
     */
    private function getMarketData()
    {
        return [
            'total_tvl'         => 50000000,
            'tvl_24h_change'    => 5.2,
            'total_volume_24h'  => 10000000,
            'volume_24h_change' => 12.5,
            'total_fees_24h'    => 30000,
            'avg_apy'           => 15.7,
        ];
    }

    /**
     * Get user position in specific pool.
     */
    private function getUserPositionInPool($poolId)
    {
        $positions = $this->getUserLiquidityPositions();

        return $positions->firstWhere('pool_id', $poolId);
    }

    /**
     * Get pool price history.
     */
    private function getPoolPriceHistory($poolId)
    {
        // Mock data for now
        $now = now();
        $history = [];

        for ($i = 23; $i >= 0; $i--) {
            $history[] = [
                'timestamp' => $now->copy()->subHours($i)->toIso8601String(),
                'price'     => 1.0 + (mt_rand(-50, 50) / 1000),
                'volume'    => mt_rand(100000, 500000),
            ];
        }

        return $history;
    }
}
