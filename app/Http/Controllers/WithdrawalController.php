<?php

namespace App\Http\Controllers;

use App\Domain\Payment\Services\PaymentGatewayService;
use App\Models\User;
use App\Support\DecimalString;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Withdrawals',
    description: 'Fiat withdrawal to a bank account'
)]
class WithdrawalController extends Controller
{
    protected PaymentGatewayService $paymentGateway;

    public function __construct(PaymentGatewayService $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

        #[OA\Get(
            path: '/withdrawals/create',
            operationId: 'withdrawalsCreate',
            tags: ['Withdrawals'],
            summary: 'Show withdrawal form',
            description: 'Shows the fiat withdrawal form',
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
    public function create()
    {
        $user = Auth::user();
        /** @var User $user */
        $account = $user->accounts()->first();

        if (! $account) {
            return redirect()->route('dashboard')
                ->with('error', 'Please create an account first.');
        }

        // Get account balances
        $balances = $account->balances()->with('asset')->get();

        return view(
            'wallet.withdraw-bank',
            [
                'account'  => $account,
                'balances' => $balances,
            ]
        );
    }

        #[OA\Post(
            path: '/withdrawals',
            operationId: 'withdrawalsStore',
            tags: ['Withdrawals'],
            summary: 'Initiate withdrawal',
            description: 'Initiates a new fiat withdrawal',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function store(Request $request)
    {
        $request->validate(
            [
                'amount'              => 'required|numeric|min:10',
                'currency'            => 'required|in:USD,EUR,GBP',
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
        /** @var \App\Domain\Account\Models\Account|null $account */
        $account = $user->accounts()->first();

        if (! $account) {
            return back()->with('error', 'No account found.');
        }

        // Exact cents: a float product truncates amounts such as 10.29 to 1028.
        $amount = DecimalString::fromNumeric($request->input('amount'), 2);
        if ($amount === null) {
            return back()->with('error', 'Enter a valid amount.');
        }
        $amountInCents = (int) bcmul($amount, '100', 0);

        // Check balance
        $balance = $account->getBalance($request->currency);
        if ($balance < $amountInCents) {
            return back()->with('error', 'Insufficient balance.');
        }

        $bankDetails = [
            'bank_name'           => $request->bank_name,
            'account_number'      => $request->account_number,
            'account_holder_name' => $request->account_holder_name,
            'routing_number'      => $request->routing_number,
            'iban'                => $request->iban,
            'swift'               => $request->swift,
        ];

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
}
