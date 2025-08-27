<?php

namespace App\Http\Controllers;

use App\Domain\Exchange\Services\LiquidityPoolService;
use App\Domain\Exchange\ValueObjects\LiquidityAdditionInput;
use App\Domain\Exchange\ValueObjects\LiquidityRemovalInput;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiquidityController extends Controller
{
    public function __construct(
        private readonly LiquidityPoolService $liquidityService
    ) {
    }

    public function index(): View
    {
        $pools = $this->liquidityService->getActivePools();
        $userPositions = auth()->check()
            ? $this->liquidityService->getProviderPositions(auth()->user()->account->id)
            : collect();

        return view('liquidity.index', compact('pools', 'userPositions'));
    }

    public function pool(string $poolId): View
    {
        $pool = $this->liquidityService->getPool($poolId);

        if (! $pool) {
            abort(404, 'Liquidity pool not found');
        }

        $metrics = $this->liquidityService->getPoolMetrics($poolId);
        $userPosition = null;

        if (auth()->check()) {
            $userPosition = $pool->providers()
                ->where('provider_id', auth()->user()->account->id)
                ->first();
        }

        return view('liquidity.pool', compact('pool', 'metrics', 'userPosition'));
    }

    public function addLiquidity(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate(
            [
                'pool_id'      => 'required|uuid',
                'base_amount'  => 'required|numeric|min:0.00000001',
                'quote_amount' => 'required|numeric|min:0.00000001',
                'min_shares'   => 'nullable|numeric|min:0',
            ]
        );

        try {
            $result = $this->liquidityService->addLiquidity(
                new LiquidityAdditionInput(
                    poolId: $validated['pool_id'],
                    providerId: auth()->user()->account->id,
                    baseCurrency: $request->base_currency,
                    quoteCurrency: $request->quote_currency,
                    baseAmount: $validated['base_amount'],
                    quoteAmount: $validated['quote_amount'],
                    minShares: $validated['min_shares'] ?? '0'
                )
            );

            if ($result['success']) {
                return redirect()
                    ->route('liquidity.pool', $validated['pool_id'])
                    ->with('success', 'Liquidity added successfully. Shares minted: ' . $result['shares_minted']);
            } else {
                return back()->with('error', 'Failed to add liquidity: ' . $result['error']);
            }
        } catch (Exception $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function removeLiquidity(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate(
            [
                'pool_id'          => 'required|uuid',
                'shares'           => 'required|numeric|min:0.00000001',
                'min_base_amount'  => 'nullable|numeric|min:0',
                'min_quote_amount' => 'nullable|numeric|min:0',
            ]
        );

        try {
            $result = $this->liquidityService->removeLiquidity(
                new LiquidityRemovalInput(
                    poolId: $validated['pool_id'],
                    providerId: auth()->user()->account->id,
                    shares: $validated['shares'],
                    minBaseAmount: $validated['min_base_amount'] ?? '0',
                    minQuoteAmount: $validated['min_quote_amount'] ?? '0'
                )
            );

            if ($result['success']) {
                return redirect()
                    ->route('liquidity.pool', $validated['pool_id'])
                    ->with(
                        'success',
                        sprintf(
                            'Liquidity removed successfully. Received: %s %s and %s %s',
                            $result['base_amount'],
                            $request->base_currency,
                            $result['quote_amount'],
                            $request->quote_currency
                        )
                    );
            } else {
                return back()->with('error', 'Failed to remove liquidity: ' . $result['error']);
            }
        } catch (Exception $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function claimRewards(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate(
            [
                'pool_id' => 'required|uuid',
            ]
        );

        try {
            $rewards = $this->liquidityService->claimRewards(
                $validated['pool_id'],
                auth()->user()->account->id
            );

            $rewardText = collect($rewards)
                ->map(fn ($amount, $currency) => "$amount $currency")
                ->join(', ');

            return redirect()
                ->route('liquidity.pool', $validated['pool_id'])
                ->with('success', "Rewards claimed: $rewardText");
        } catch (Exception $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
