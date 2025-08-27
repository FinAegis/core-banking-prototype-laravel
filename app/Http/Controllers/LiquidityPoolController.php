<?php

namespace App\Http\Controllers;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\Exchange\Contracts\ExchangeServiceInterface;
use App\Domain\Exchange\Contracts\LiquidityPoolServiceInterface;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LiquidityPoolController extends Controller
{
    public function __construct(
        private LiquidityPoolServiceInterface $liquidityPoolService,
        private ExchangeServiceInterface $exchangeService
    ) {
    }

    /**
     * Display liquidity pools dashboard.
     */
    public function index()
    {
        $pools = $this->liquidityPoolService->getAllPools();
        $userLiquidity = $this->getUserLiquidityPositions();
        $marketData = $this->getMarketData();

        return view('liquidity.index', compact('pools', 'userLiquidity', 'marketData'));
    }

    /**
     * Show specific pool details.
     */
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
     * Show add liquidity form.
     */
    public function create($poolId)
    {
        $poolProjection = $this->liquidityPoolService->getPool($poolId);

        if (! $poolProjection) {
            abort(404, 'Pool not found');
        }

        $pool = [
            'id'             => $poolProjection->pool_id,
            'base_currency'  => $poolProjection->base_currency,
            'quote_currency' => $poolProjection->quote_currency,
            'fee_rate'       => $poolProjection->fee_rate,
        ];

        $userBalances = $this->getUserBalances($pool);
        $metrics = $this->liquidityPoolService->getPoolMetrics($poolId);

        return view('liquidity.add', compact('pool', 'userBalances', 'metrics'));
    }

    /**
     * Add liquidity to pool.
     */
    public function store(Request $request, $poolId)
    {
        $validated = $request->validate(
            [
                'account_id'         => 'required|uuid',
                'base_amount'        => 'required|numeric|min:0.01',
                'quote_amount'       => 'required|numeric|min:0.01',
                'slippage_tolerance' => 'required|numeric|min:0.1|max:50',
            ]
        );

        $poolProjection = $this->liquidityPoolService->getPool($poolId);

        if (! $poolProjection) {
            abort(404, 'Pool not found');
        }

        $account = Account::where('uuid', $validated['account_id'])
            ->whereHas('user', function ($query) {
                $query->where('id', Auth::id());
            })
            ->first();

        if (! $account) {
            return back()->withErrors(['account_id' => 'Invalid account']);
        }

        try {
            $result = $this->liquidityPoolService->addLiquidity(
                $poolId,
                AccountUuid::fromString($account->uuid),
                (int) ($validated['base_amount'] * 100),
                (int) ($validated['quote_amount'] * 100),
                $validated['slippage_tolerance']
            );

            return redirect()
                ->route('liquidity.show', $poolId)
                ->with('success', 'Successfully added liquidity to the pool');
        } catch (Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to add liquidity: ' . $e->getMessage()]);
        }
    }

    /**
     * Show remove liquidity form.
     */
    public function remove($poolId)
    {
        $poolProjection = $this->liquidityPoolService->getPool($poolId);

        if (! $poolProjection) {
            abort(404, 'Pool not found');
        }

        $pool = [
            'id'             => $poolProjection->pool_id,
            'base_currency'  => $poolProjection->base_currency,
            'quote_currency' => $poolProjection->quote_currency,
            'fee_rate'       => $poolProjection->fee_rate,
        ];

        $userPosition = $this->getUserPositionInPool($poolId);

        if (! $userPosition) {
            return redirect()
                ->route('liquidity.show', $poolId)
                ->withErrors(['error' => 'You have no liquidity in this pool']);
        }

        $metrics = $this->liquidityPoolService->getPoolMetrics($poolId);

        return view('liquidity.remove', compact('pool', 'userPosition', 'metrics'));
    }

    /**
     * Remove liquidity from pool.
     */
    public function destroy(Request $request, $poolId)
    {
        $validated = $request->validate(
            [
                'account_id'           => 'required|uuid',
                'liquidity_percentage' => 'required|numeric|min:1|max:100',
                'min_base_amount'      => 'required|numeric|min:0',
                'min_quote_amount'     => 'required|numeric|min:0',
            ]
        );

        $poolProjection = $this->liquidityPoolService->getPool($poolId);

        if (! $poolProjection) {
            abort(404, 'Pool not found');
        }

        $account = Account::where('uuid', $validated['account_id'])
            ->whereHas('user', function ($query) {
                $query->where('id', Auth::id());
            })
            ->first();

        if (! $account) {
            return back()->withErrors(['account_id' => 'Invalid account']);
        }

        try {
            $result = $this->liquidityPoolService->removeLiquidity(
                $poolId,
                AccountUuid::fromString($account->uuid),
                $validated['liquidity_percentage'],
                (int) ($validated['min_base_amount'] * 100),
                (int) ($validated['min_quote_amount'] * 100)
            );

            return redirect()
                ->route('liquidity.show', $poolId)
                ->with('success', 'Successfully removed liquidity from the pool');
        } catch (Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to remove liquidity: ' . $e->getMessage()]);
        }
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
     * Get user balances for pool currencies.
     */
    private function getUserBalances($pool)
    {
        $user = Auth::user();
        /** @var User $user */
        if (! $user) {
            return [];
        }

        $accounts = $user->accounts()->with('balances.asset')->get();

        $balances = [];
        foreach ($accounts as $account) {
            $baseBalance = $account->balances->where('asset_code', $pool['base_currency'])->first();
            $quoteBalance = $account->balances->where('asset_code', $pool['quote_currency'])->first();

            if ($baseBalance || $quoteBalance) {
                $balances[] = [
                    'account_id'    => $account->uuid,
                    'account_name'  => $account->name,
                    'base_balance'  => $baseBalance ? $baseBalance->balance / 100 : 0,
                    'quote_balance' => $quoteBalance ? $quoteBalance->balance / 100 : 0,
                ];
            }
        }

        return $balances;
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
