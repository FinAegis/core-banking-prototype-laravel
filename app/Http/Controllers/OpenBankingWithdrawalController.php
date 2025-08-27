<?php

namespace App\Http\Controllers;

use App\Domain\Account\Models\Account;
use App\Domain\Banking\Services\BankIntegrationService;
use App\Domain\Payment\Services\PaymentGatewayService;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class OpenBankingWithdrawalController extends Controller
{
    protected BankIntegrationService $bankIntegration;

    protected PaymentGatewayService $paymentGateway;

    public function __construct(
        BankIntegrationService $bankIntegration,
        PaymentGatewayService $paymentGateway
    ) {
        $this->bankIntegration = $bankIntegration;
        $this->paymentGateway = $paymentGateway;
    }

    /**
     * Show the OpenBanking withdrawal form.
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

        // Get user's connected banks
        $connectedBanks = $this->bankIntegration->getUserBankConnections($user);

        // Get available banks for connection
        $availableBanks = $this->bankIntegration->getAvailableConnectors()
            ->map(
                function ($connector, $bankCode) {
                    return [
                        'code'                 => $bankCode,
                        'name'                 => $connector->getBankName(),
                        'logo'                 => $connector->getLogoUrl(),
                        'supported_currencies' => $connector->getSupportedCurrencies(),
                    ];
                }
            );

        // Get account balances
        $balances = $account->balances()->with('asset')->get();

        return view(
            'wallet.withdraw-openbanking',
            [
                'account'        => $account,
                'connectedBanks' => $connectedBanks,
                'availableBanks' => $availableBanks,
                'balances'       => $balances,
            ]
        );
    }

    /**
     * Initiate bank connection for withdrawal.
     */
    public function initiate(Request $request)
    {
        $request->validate(
            [
                'bank_code' => 'required|string',
                'amount'    => 'required|numeric|min:10',
                'currency'  => 'required|in:USD,EUR,GBP',
            ]
        );

        $user = Auth::user();
        /** @var User $user */
        $account = $user->accounts()->first();

        // Check balance
        $amountInCents = (int) ($request->amount * 100);
        $balance = $account->getBalance($request->currency);

        if ($balance < $amountInCents) {
            return back()->with('error', 'Insufficient balance.');
        }

        // Store withdrawal details in session
        Session::put(
            'openbanking_withdrawal',
            [
                'amount'       => $amountInCents,
                'currency'     => $request->currency,
                'bank_code'    => $request->bank_code,
                'account_uuid' => $account->uuid,
            ]
        );

        try {
            // Get bank connector
            $connector = $this->bankIntegration->getConnector($request->bank_code);

            // Generate OAuth URL for bank authorization
            $authUrl = $connector->getAuthorizationUrl(
                [
                    'redirect_uri' => route('wallet.withdraw.openbanking.callback'),
                    'scope'        => 'accounts payments',
                    'state'        => csrf_token(),
                ]
            );

            return redirect($authUrl);
        } catch (Exception $e) {
            Log::error(
                'Failed to initiate OpenBanking connection',
                [
                    'user_id'   => $user->id,
                    'bank_code' => $request->bank_code,
                    'error'     => $e->getMessage(),
                ]
            );

            return back()->with('error', 'Failed to connect to bank. Please try again.');
        }
    }

    /**
     * Handle bank authorization callback.
     */
    public function callback(Request $request)
    {
        // Verify state to prevent CSRF
        if ($request->state !== csrf_token()) {
            return redirect()->route('wallet.withdraw.create')
                ->with('error', 'Invalid authorization state.');
        }

        // Get withdrawal details from session
        $withdrawalDetails = Session::get('openbanking_withdrawal');
        if (! $withdrawalDetails) {
            return redirect()->route('wallet.withdraw.create')
                ->with('error', 'Withdrawal session expired. Please try again.');
        }

        $user = Auth::user();
        /** @var User $user */
        DB::beginTransaction();
        try {
            // Exchange authorization code for access token
            $connector = $this->bankIntegration->getConnector($withdrawalDetails['bank_code']);
            $credentials = $connector->exchangeAuthorizationCode(
                $request->code,
                [
                    'redirect_uri' => route('wallet.withdraw.openbanking.callback'),
                ]
            );

            // Connect user to bank if not already connected
            $connections = $this->bankIntegration->getUserBankConnections($user);
            $isConnected = $connections->contains(
                function ($connection) use ($withdrawalDetails) {
                    return $connection->bankCode === $withdrawalDetails['bank_code'] && $connection->isActive();
                }
            );

            if (! $isConnected) {
                $this->bankIntegration->connectUserToBank(
                    $user,
                    $withdrawalDetails['bank_code'],
                    $credentials
                );
            }

            // Get user's bank accounts
            $bankAccounts = $this->bankIntegration->getUserBankAccounts($user, $withdrawalDetails['bank_code']);

            if ($bankAccounts->isEmpty()) {
                throw new Exception('No bank accounts found.');
            }

            // For simplicity, use the first account
            // In production, you'd let the user select
            $bankAccount = $bankAccounts->first();

            // Create withdrawal request
            $account = Account::where('uuid', $withdrawalDetails['account_uuid'])->firstOrFail();
            $result = $this->paymentGateway->createWithdrawalRequest(
                $account,
                $withdrawalDetails['amount'],
                $withdrawalDetails['currency'],
                [
                    'bank_name'           => $connector->getBankName(),
                    'account_number'      => $bankAccount->accountNumber,
                    'account_holder_name' => $bankAccount->holderName ?? $user->name,
                    'routing_number'      => $bankAccount->routingNumber ?? null,
                    'iban'                => $bankAccount->iban,
                    'swift'               => $bankAccount->swift,
                    'bank_account_id'     => $bankAccount->id,
                    'transfer_type'       => 'openbanking',
                ]
            );

            // Initiate the actual bank transfer
            $transfer = $connector->initiatePayment(
                [
                    'to_account' => [
                        'iban'           => $bankAccount->iban,
                        'account_number' => $bankAccount->accountNumber,
                        'holder_name'    => $bankAccount->holderName ?? $user->name,
                    ],
                    'amount'      => $withdrawalDetails['amount'],
                    'currency'    => $withdrawalDetails['currency'],
                    'reference'   => $result['reference'],
                    'description' => 'Withdrawal from FinAegis account',
                ]
            );

            DB::commit();

            // Clear session
            Session::forget('openbanking_withdrawal');

            return redirect()->route('wallet.index')
                ->with('success', 'Withdrawal initiated successfully via OpenBanking. You will receive the funds within 1-2 business days.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error(
                'OpenBanking withdrawal failed',
                [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]
            );

            return redirect()->route('wallet.withdraw.create')
                ->with('error', 'Failed to process withdrawal: ' . $e->getMessage());
        }
    }

    /**
     * Show connected bank accounts for withdrawal.
     */
    public function selectAccount(Request $request)
    {
        $request->validate(
            [
                'bank_code' => 'required|string',
                'amount'    => 'required|numeric|min:10',
                'currency'  => 'required|in:USD,EUR,GBP',
            ]
        );

        $user = Auth::user();
        /** @var User $user */
        $account = $user->accounts()->first();

        // Get user's bank accounts for the selected bank
        $bankAccounts = $this->bankIntegration->getUserBankAccounts($user, $request->bank_code);

        if ($bankAccounts->isEmpty()) {
            return redirect()->route('wallet.withdraw.openbanking.initiate', $request->all());
        }

        return view(
            'wallet.withdraw-openbanking-accounts',
            [
                'account'      => $account,
                'bankAccounts' => $bankAccounts,
                'bankCode'     => $request->bank_code,
                'amount'       => $request->amount,
                'currency'     => $request->currency,
            ]
        );
    }

    /**
     * Process withdrawal with selected bank account.
     */
    public function processWithAccount(Request $request)
    {
        $request->validate(
            [
                'bank_code'       => 'required|string',
                'bank_account_id' => 'required|string',
                'amount'          => 'required|numeric|min:10',
                'currency'        => 'required|in:USD,EUR,GBP',
            ]
        );

        $user = Auth::user();
        /** @var User $user */
        /** @var Account|null $account */
        $account = $user->accounts()->first();

        if (! $account) {
            return back()->with('error', 'No account found.');
        }

        // Verify the bank account belongs to the user
        $bankAccounts = $this->bankIntegration->getUserBankAccounts($user, $request->bank_code);
        $selectedAccount = $bankAccounts->firstWhere('id', $request->bank_account_id);

        if (! $selectedAccount) {
            return back()->with('error', 'Invalid bank account selected.');
        }

        $amountInCents = (int) ($request->amount * 100);

        DB::beginTransaction();
        try {
            // Create withdrawal request
            $result = $this->paymentGateway->createWithdrawalRequest(
                $account,
                $amountInCents,
                $request->currency,
                [
                    'bank_name'           => $this->bankIntegration->getConnector($request->bank_code)->getBankName(),
                    'account_number'      => $selectedAccount->accountNumber,
                    'account_holder_name' => $selectedAccount->holderName ?? $user->name,
                    'routing_number'      => $selectedAccount->routingNumber ?? null,
                    'iban'                => $selectedAccount->iban,
                    'swift'               => $selectedAccount->swift,
                    'bank_account_id'     => $selectedAccount->id,
                    'transfer_type'       => 'openbanking',
                ]
            );

            // Initiate the bank transfer
            $connector = $this->bankIntegration->getConnector($request->bank_code);
            $transfer = $connector->initiatePayment(
                [
                    'to_account' => [
                        'id'             => $selectedAccount->id,
                        'iban'           => $selectedAccount->iban,
                        'account_number' => $selectedAccount->accountNumber,
                        'holder_name'    => $selectedAccount->holderName ?? $user->name,
                    ],
                    'amount'      => $amountInCents,
                    'currency'    => $request->currency,
                    'reference'   => $result['reference'],
                    'description' => 'Withdrawal from FinAegis account',
                ]
            );

            DB::commit();

            return redirect()->route('wallet.index')
                ->with('success', 'Withdrawal initiated successfully. Funds will arrive in 1-2 business days.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error(
                'OpenBanking withdrawal failed',
                [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]
            );

            return back()->with('error', 'Failed to process withdrawal: ' . $e->getMessage());
        }
    }
}
