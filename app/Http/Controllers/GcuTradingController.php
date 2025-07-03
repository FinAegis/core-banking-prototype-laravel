<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\Basket\Models\BasketPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GcuTradingController extends Controller
{
    /**
     * Display the GCU trading interface
     */
    public function index()
    {
        $user = Auth::user();
        $accounts = $user->accounts()->with('balances.asset')->get();
        
        // Get GCU asset
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
            ->where(function($query) {
                $query->where('transaction_projections.currency', 'GCU')
                    ->orWhere('transaction_projections.metadata->target_currency', 'GCU');
            })
            ->select(
                'transaction_projections.*',
                'accounts.name as account_name'
            )
            ->orderBy('transaction_projections.created_at', 'desc')
            ->limit(10)
            ->get();
        
        return view('gcu.trading', compact(
            'accounts',
            'gcuAsset',
            'currentPrice',
            'gcuBalance',
            'usdBalance',
            'priceHistory',
            'recentTrades'
        ));
    }
}