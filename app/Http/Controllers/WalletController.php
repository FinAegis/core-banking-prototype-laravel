<?php

namespace App\Http\Controllers;

use App\Domain\Account\Aggregates\LedgerAggregate;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Domain\Account\Workflows\DepositAccountWorkflow;
use App\Domain\Account\Workflows\WithdrawAccountWorkflow;
use App\Domain\Payment\Workflows\TransferWorkflow;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Workflows\AssetDepositWorkflow;
use App\Domain\Asset\Workflows\AssetWithdrawWorkflow;
use App\Domain\Asset\Workflows\AssetTransferWorkflow;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Workflow\WorkflowStub;

class WalletController extends Controller
{
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
     * Process a deposit
     */
    public function deposit(Request $request)
    {
        $validated = $request->validate([
            'account_uuid' => 'required|exists:accounts,uuid',
            'amount' => 'required|numeric|min:0.01',
            'asset_code' => 'required|exists:assets,code',
        ]);

        $account = Account::where('uuid', $validated['account_uuid'])->firstOrFail();
        
        // Ensure account belongs to authenticated user
        if ($account->user_uuid !== Auth::user()->uuid) {
            abort(403, 'Unauthorized');
        }

        $amount = (int) ($validated['amount'] * 100); // Convert to cents
        $assetCode = $validated['asset_code'];

        // For now, directly update balance (can be replaced with workflows later)
        try {
            $account->addBalance($assetCode, $amount);
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => 'Deposit failed: ' . $e->getMessage()]);
        }

        return redirect()->route('dashboard')->with('success', 'Deposit successful!');
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
     * Process a withdrawal
     */
    public function withdraw(Request $request)
    {
        $validated = $request->validate([
            'account_uuid' => 'required|exists:accounts,uuid',
            'amount' => 'required|numeric|min:0.01',
            'asset_code' => 'required|exists:assets,code',
        ]);

        $account = Account::where('uuid', $validated['account_uuid'])->firstOrFail();
        
        // Ensure account belongs to authenticated user
        if ($account->user_uuid !== Auth::user()->uuid) {
            abort(403, 'Unauthorized');
        }

        $amount = (int) ($validated['amount'] * 100);
        $assetCode = $validated['asset_code'];

        // Check balance
        $balance = $account->getBalance($assetCode);
        if ($balance < $amount) {
            return back()->withErrors(['amount' => 'Insufficient balance']);
        }

        // For now, directly update balance (can be replaced with workflows later)
        try {
            $account->subtractBalance($assetCode, $amount);
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => 'Withdrawal failed: ' . $e->getMessage()]);
        }

        return redirect()->route('dashboard')->with('success', 'Withdrawal successful!');
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
     * Process a transfer
     */
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'from_account_uuid' => 'required|exists:accounts,uuid',
            'to_account_uuid' => 'required|exists:accounts,uuid|different:from_account_uuid',
            'amount' => 'required|numeric|min:0.01',
            'asset_code' => 'required|exists:assets,code',
            'reference' => 'nullable|string|max:255',
        ]);

        $fromAccount = Account::where('uuid', $validated['from_account_uuid'])->firstOrFail();
        $toAccount = Account::where('uuid', $validated['to_account_uuid'])->firstOrFail();
        
        // Ensure from account belongs to authenticated user
        if ($fromAccount->user_uuid !== Auth::user()->uuid) {
            abort(403, 'Unauthorized');
        }

        $amount = (int) ($validated['amount'] * 100);
        $assetCode = $validated['asset_code'];

        // Check balance
        $balance = $fromAccount->getBalance($assetCode);
        if ($balance < $amount) {
            return back()->withErrors(['amount' => 'Insufficient balance']);
        }

        // For now, directly update balances (can be replaced with workflows later)
        try {
            // Subtract from source account
            $fromAccount->subtractBalance($assetCode, $amount);
            
            // Add to destination account
            $toAccount->addBalance($assetCode, $amount);
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => 'Transfer failed: ' . $e->getMessage()]);
        }

        return redirect()->route('dashboard')->with('success', 'Transfer successful!');
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
     * Process a currency conversion
     */
    public function convert(Request $request)
    {
        $validated = $request->validate([
            'account_uuid' => 'required|exists:accounts,uuid',
            'from_asset' => 'required|exists:assets,code',
            'to_asset' => 'required|exists:assets,code|different:from_asset',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $account = Account::where('uuid', $validated['account_uuid'])->firstOrFail();
        
        // Ensure account belongs to authenticated user
        if ($account->user_uuid !== Auth::user()->uuid) {
            abort(403, 'Unauthorized');
        }

        $fromAsset = $validated['from_asset'];
        $toAsset = $validated['to_asset'];
        $amount = (int) ($validated['amount'] * 100);

        // Check balance
        $balance = $account->getBalance($fromAsset);
        if ($balance < $amount) {
            return back()->withErrors(['amount' => 'Insufficient balance']);
        }

        // Get exchange rate
        $rate = ExchangeRate::getRate($fromAsset, $toAsset);
        if (!$rate) {
            return back()->withErrors(['to_asset' => 'Exchange rate not available']);
        }

        $convertedAmount = (int) round($amount * $rate);

        // For now, directly update balances (can be replaced with workflows later)
        try {
            // Subtract from source asset
            $account->subtractBalance($fromAsset, $amount);
            
            // Add to target asset
            $account->addBalance($toAsset, $convertedAmount);
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => 'Conversion failed: ' . $e->getMessage()]);
        }

        return redirect()->route('dashboard')->with('success', 
            sprintf('Converted %s %s to %s %s at rate %s', 
                number_format($amount / 100, 2),
                $fromAsset,
                number_format($convertedAmount / 100, 2),
                $toAsset,
                number_format($rate, 4)
            )
        );
    }
}