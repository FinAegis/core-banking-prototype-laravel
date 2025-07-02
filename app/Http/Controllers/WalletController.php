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
        
        return view('wallet.withdraw-options', compact('account', 'balances'));
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

    /**
     * Show the transactions list
     */
    public function transactions()
    {
        $account = Auth::user()->accounts()->first();
        
        if (!$account) {
            return view('wallet.transactions', [
                'account' => null,
                'transactions' => collect(),
                'transactionsJson' => json_encode([])
            ]);
        }
        
        // Get transactions from the Account's transactions relationship
        $transactions = $account->transactions()
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();
        
        // Transform transactions for the view
        $transformedTransactions = $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'created_at' => $transaction->created_at->toISOString(),
                'type' => $this->mapTransactionType($transaction->type),
                'description' => $transaction->description,
                'reference' => $transaction->reference ?? '',
                'asset_code' => $transaction->asset_code,
                'asset_symbol' => $this->getAssetSymbol($transaction->asset_code),
                'amount' => $transaction->type === 'debit' ? -$transaction->amount : $transaction->amount,
                'balance_after' => $transaction->balance_after ?? 0
            ];
        });
        
        return view('wallet.transactions', [
            'account' => $account,
            'transactions' => $transactions,
            'transactionsJson' => $transformedTransactions->toJson()
        ]);
    }
    
    private function mapTransactionType($type)
    {
        $typeMap = [
            'credit' => 'deposit',
            'debit' => 'withdrawal',
            'transfer_credit' => 'transfer_in',
            'transfer_debit' => 'transfer_out',
            'conversion' => 'conversion'
        ];
        
        return $typeMap[$type] ?? $type;
    }
    
    private function getAssetSymbol($assetCode)
    {
        $symbols = [
            'GCU' => 'Ǥ',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£'
        ];
        
        return $symbols[$assetCode] ?? $assetCode;
    }

}