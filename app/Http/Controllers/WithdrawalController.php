<?php

namespace App\Http\Controllers;

use App\Domain\Banking\Models\BankAccountModel;
use App\Domain\Payment\Services\PaymentGatewayService;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WithdrawalController extends Controller
{
    protected PaymentGatewayService $paymentGateway;

    public function __construct(PaymentGatewayService $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    /**
     * Show the withdrawal form.
     */
    public function create()
    {
        $user = Auth::user();
        /** @var User $user */
        $account = $user->accounts()->first();

        if (! $account) {
            return redirect()->route('dashboard')
                ->with('error', 'Please create an account first.');
        }

        // Get user's saved bank accounts
        $bankAccounts = $user->bankAccounts()
            ->where('verified', true)
            ->get();

        // Get account balances
        $balances = $account->balances()->with('asset')->get();

        return view(
            'wallet.withdraw-bank',
            [
                'account'      => $account,
                'bankAccounts' => $bankAccounts,
                'balances'     => $balances,
            ]
        );
    }

    /**
     * Process a withdrawal request.
     */
    public function store(Request $request)
    {
        $request->validate(
            [
                'amount'            => 'required|numeric|min:10',
                'currency'          => 'required|in:USD,EUR,GBP',
                'bank_account_id'   => 'required_if:bank_account_type,saved|exists:bank_accounts,id',
                'bank_account_type' => 'required|in:saved,new',
                // New bank account fields
                'bank_name'           => 'required_if:bank_account_type,new|string|max:255',
                'account_number'      => 'required_if:bank_account_type,new|string|max:50',
                'account_holder_name' => 'required_if:bank_account_type,new|string|max:255',
                'routing_number'      => 'nullable|string|max:20',
                'iban'                => 'nullable|string|max:50',
                'swift'               => 'nullable|string|max:20',
                'save_bank_account'   => 'boolean',
            ]
        );

        $user = Auth::user();
        /** @var User $user */
        /** @var \App\Domain\Account\Models\Account|null $account */
        $account = $user->accounts()->first();

        if (! $account) {
            return back()->with('error', 'No account found.');
        }

        $amountInCents = (int) ($request->amount * 100);

        // Check balance
        $balance = $account->getBalance($request->currency);
        if ($balance < $amountInCents) {
            return back()->with('error', 'Insufficient balance.');
        }

        // Get or create bank details
        if ($request->bank_account_type === 'saved') {
            $bankAccount = $user->bankAccounts()->findOrFail($request->bank_account_id);
            $bankDetails = [
                'bank_name'           => $bankAccount->bank_name,
                'account_number'      => $bankAccount->account_number,
                'account_holder_name' => $bankAccount->account_holder_name,
                'routing_number'      => $bankAccount->routing_number,
                'iban'                => $bankAccount->iban,
                'swift'               => $bankAccount->swift,
            ];
        } else {
            $bankDetails = [
                'bank_name'           => $request->bank_name,
                'account_number'      => $request->account_number,
                'account_holder_name' => $request->account_holder_name,
                'routing_number'      => $request->routing_number,
                'iban'                => $request->iban,
                'swift'               => $request->swift,
            ];

            // Save bank account if requested
            if ($request->save_bank_account) {
                $user->bankAccounts()->create(
                    [
                        'bank_name'                => $bankDetails['bank_name'],
                        'account_number'           => substr($bankDetails['account_number'], -4),
                        'account_number_encrypted' => encrypt($bankDetails['account_number']),
                        'account_holder_name'      => $bankDetails['account_holder_name'],
                        'routing_number'           => $bankDetails['routing_number'],
                        'iban'                     => $bankDetails['iban'],
                        'swift'                    => $bankDetails['swift'],
                        'verified'                 => false, // Requires verification
                    ]
                );
            }
        }

        try {
            $result = $this->paymentGateway->createWithdrawalRequest(
                $account,
                $amountInCents,
                $request->currency,
                $bankDetails
            );

            return redirect()->route('wallet.index')
                ->with('success', 'Withdrawal request submitted successfully. Processing time: 1-3 business days.');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to process withdrawal: ' . $e->getMessage());
        }
    }

    /**
     * Add a new bank account.
     */
    public function addBankAccount(Request $request)
    {
        $request->validate(
            [
                'bank_name'           => 'required|string|max:255',
                'account_number'      => 'required|string|max:50',
                'account_holder_name' => 'required|string|max:255',
                'routing_number'      => 'nullable|string|max:20',
                'iban'                => 'nullable|string|max:50',
                'swift'               => 'nullable|string|max:20',
            ]
        );

        $user = Auth::user();
        /** @var User $user */
        $bankAccount = $user->bankAccounts()->create(
            [
                'bank_name'                => $request->bank_name,
                'account_number'           => substr($request->account_number, -4),
                'account_number_encrypted' => encrypt($request->account_number),
                'account_holder_name'      => $request->account_holder_name,
                'routing_number'           => $request->routing_number,
                'iban'                     => $request->iban,
                'swift'                    => $request->swift,
                'verified'                 => false,
            ]
        );

        // In production, send verification micro-deposits or use Plaid/other verification service

        return redirect()->back()
            ->with('success', 'Bank account added. Verification required before withdrawals.');
    }

    /**
     * Remove a bank account.
     */
    public function removeBankAccount(BankAccountModel $bankAccount)
    {
        if ($bankAccount->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        $bankAccount->delete();

        return redirect()->back()
            ->with('success', 'Bank account removed successfully.');
    }
}
