<?php

namespace App\Http\Controllers;

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Wallet",
 *     description="Multi-asset wallet management"
 * )
 */
class WalletController extends Controller
{
    /**
     * @OA\Get(
     *     path="/wallet",
     *     operationId="walletIndex",
     *     tags={"Wallet"},
     *     summary="Wallet dashboard",
     *     description="Returns the wallet overview dashboard",
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
        $account = $user->accounts()->first();

        return view('wallet.index', compact('account'));
    }

    /**
     * @OA\Get(
     *     path="/wallet/deposit",
     *     operationId="walletShowDeposit",
     *     tags={"Wallet"},
     *     summary="Show deposit page",
     *     description="Shows the wallet deposit page",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function showDeposit()
    {
        $account = Auth::user()->accounts()->first();
        $assets = Asset::where('is_active', true)->get();

        return view('wallet.deposit', compact('account', 'assets'));
    }

    /**
     * @OA\Get(
     *     path="/wallet/withdraw",
     *     operationId="walletShowWithdraw",
     *     tags={"Wallet"},
     *     summary="Show withdraw page",
     *     description="Shows the wallet withdrawal page",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function showWithdraw()
    {
        $account = Auth::user()->accounts()->first();
        $balances = $account ? $account->balances()->with('asset')->where('balance', '>', 0)->get() : collect();

        return view('wallet.withdraw-options', compact('account', 'balances'));
    }

    /**
     * @OA\Get(
     *     path="/wallet/transfer",
     *     operationId="walletShowTransfer",
     *     tags={"Wallet"},
     *     summary="Show transfer page",
     *     description="Shows the wallet transfer page",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function showTransfer()
    {
        $account = Auth::user()->accounts()->first();
        $balances = $account ? $account->balances()->with('asset')->where('balance', '>', 0)->get() : collect();

        return view('wallet.transfer', compact('account', 'balances'));
    }

    /**
     * @OA\Get(
     *     path="/wallet/convert",
     *     operationId="walletShowConvert",
     *     tags={"Wallet"},
     *     summary="Show convert page",
     *     description="Shows the wallet currency conversion page",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
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
     * @OA\Get(
     *     path="/wallet/transactions",
     *     operationId="walletTransactions",
     *     tags={"Wallet"},
     *     summary="Wallet transactions",
     *     description="Returns wallet transaction history",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function transactions()
    {
        $account = Auth::user()->accounts()->first();

        if (! $account) {
            return view(
                'wallet.transactions',
                [
                    'account'          => null,
                    'transactions'     => collect(),
                    'transactionsJson' => json_encode([]),
                ]
            );
        }

        // Get transactions from the Account's transactions relationship
        $transactions = $account->transactions()
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        // Transform transactions for the view
        $transformedTransactions = $transactions->map(
            function ($transaction) {
                return [
                    'id'            => $transaction->id,
                    'created_at'    => $transaction->created_at->toISOString(),
                    'type'          => $this->mapTransactionType($transaction->type),
                    'description'   => $transaction->description,
                    'reference'     => $transaction->reference ?? '',
                    'asset_code'    => $transaction->asset_code,
                    'asset_symbol'  => $this->getAssetSymbol($transaction->asset_code),
                    'amount'        => $transaction->type === 'debit' ? -$transaction->amount : $transaction->amount,
                    'balance_after' => $transaction->balance_after ?? 0,
                ];
            }
        );

        return view(
            'wallet.transactions',
            [
                'account'          => $account,
                'transactions'     => $transactions,
                'transactionsJson' => $transformedTransactions->toJson(),
            ]
        );
    }

    private function mapTransactionType($type)
    {
        $typeMap = [
            'credit'          => 'deposit',
            'debit'           => 'withdrawal',
            'transfer_credit' => 'transfer_in',
            'transfer_debit'  => 'transfer_out',
            'conversion'      => 'conversion',
        ];

        return $typeMap[$type] ?? $type;
    }

    private function getAssetSymbol($assetCode)
    {
        $symbols = [
            'GCU' => 'Ǥ',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        ];

        return $symbols[$assetCode] ?? $assetCode;
    }
}
