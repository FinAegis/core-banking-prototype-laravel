<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    /**
     * Show the wallet dashboard
     */
    public function index()
    {
        $user = Auth::user();
        $account = $user->accounts()->first();
        
        return view('wallet.index', compact('account'));
    }

    /**
     * Show the deposit form
     */
    public function showDeposit()
    {
        $account = Auth::user()->accounts()->first();
        $assets = Asset::where('is_active', true)->get();
        
        return view('wallet.deposit', compact('account', 'assets'));
    }


    /**
     * Show the withdraw form
     */
    public function showWithdraw()
    {
        $account = Auth::user()->accounts()->first();
        $balances = $account ? $account->balances()->with('asset')->where('balance', '>', 0)->get() : collect();
        
        return view('wallet.withdraw', compact('account', 'balances'));
    }


    /**
     * Show the transfer form
     */
    public function showTransfer()
    {
        $account = Auth::user()->accounts()->first();
        $balances = $account ? $account->balances()->with('asset')->where('balance', '>', 0)->get() : collect();
        
        return view('wallet.transfer', compact('account', 'balances'));
    }


    /**
     * Show the convert form
     */
    public function showConvert()
    {
        $account = Auth::user()->accounts()->first();
        $balances = $account ? $account->balances()->with('asset')->where('balance', '>', 0)->get() : collect();
        $assets = Asset::where('is_active', true)->get();
        $rates = ExchangeRate::getLatestRates();
        
        return view('wallet.convert', compact('account', 'balances', 'assets', 'rates'));
    }

}