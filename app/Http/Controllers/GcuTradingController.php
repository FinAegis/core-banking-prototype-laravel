<?php

namespace App\Http\Controllers;

use App\Domain\Asset\Models\Asset;
use App\Domain\Basket\Models\BasketPrice;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="GCU Trading",
 *     description="GCU token trading interface"
 * )
 */
class GcuTradingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/gcu/trading",
     *     operationId="gCUTradingIndex",
     *     tags={"GCU Trading"},
     *     summary="GCU trading dashboard",
     *     description="Returns the GCU token trading interface",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index()
    {
        $user = Auth::user();
        /** @var User $user */
        $accounts = $user->accounts()->with('balances.asset')->get();

        // Get GCU asset
        /** @var Asset|null $gcuAsset */
        $gcuAsset = Asset::where('code', 'GCU')->first();

        // Get current GCU price
        $currentPrice = BasketPrice::where('basket_code', 'GCU')
            ->orderBy('created_at', 'desc')
            ->first();

        // Get user's GCU balance
        $gcuBalance = 0;
        $usdBalance = 0;

        if ($accounts->count() > 0) {
            $mainAccount = $accounts->first();
            $gcuBalance = $mainAccount->getBalance('GCU');
            $usdBalance = $mainAccount->getBalance('USD');
        }

        // Get recent price history
        $priceHistory = BasketPrice::where('basket_code', 'GCU')
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get()
            ->reverse();

        // Get recent trades
        $recentTrades = DB::table('transaction_projections')
            ->join('accounts', 'transaction_projections.account_uuid', '=', 'accounts.uuid')
            ->where('accounts.user_uuid', $user->uuid)
            ->where('transaction_projections.type', 'exchange')
            ->where('transaction_projections.status', 'completed')
            ->where(
                function ($query) {
                    $query->where('transaction_projections.currency', 'GCU')
                        ->orWhere('transaction_projections.metadata->target_currency', 'GCU');
                }
            )
            ->select(
                'transaction_projections.*',
                'accounts.name as account_name'
            )
            ->orderBy('transaction_projections.created_at', 'desc')
            ->limit(10)
            ->get();

        return view(
            'gcu.trading',
            compact(
                'accounts',
                'gcuAsset',
                'currentPrice',
                'gcuBalance',
                'usdBalance',
                'priceHistory',
                'recentTrades'
            )
        );
    }
}
