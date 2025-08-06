<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OpenBankingDepositController extends Controller
{
    protected PaymentGatewayService $paymentGateway;

    protected PaymentServiceInterface $paymentService;

    public function __construct(
        PaymentGatewayService $paymentGateway,
        PaymentServiceInterface $paymentService
    ) {
        $this->paymentGateway = $paymentGateway;
        $this->paymentService = $paymentService;
    }

    public function initiate(Request $request)
    {
        $request->validate([
            'amount'   => 'required|numeric|min:1|max:10000',
            'currency' => 'required|in:USD,EUR,GBP',
            'bank'     => 'required|string',
        ]);

        $user = Auth::user();
        $account = $user->accounts()->first();

        if (! $account) {
            return redirect()->route('wallet.deposit')
                ->with('error', 'Please create an account first.');
        }

        // In demo environment or sandbox mode, simulate the bank authorization
        if (app()->environment('demo') || config('demo.sandbox.enabled')) {
            Log::info('Simulating OpenBanking deposit in demo mode', [
                'user_id' => $user->id,
                'amount'  => $request->amount,
                'bank'    => $request->bank,
            ]);

            // Store the deposit details in session
            session()->put('openbanking_deposit', [
                'amount'       => $request->amount * 100, // Convert to cents
                'currency'     => $request->currency,
                'bank'         => $request->bank,
                'account_uuid' => $account->uuid,
                'initiated_at' => now()->toIso8601String(),
            ]);

            // Simulate immediate callback (normally would redirect to bank)
            return redirect()->route('wallet.deposit.openbanking.callback')
                ->with('demo_mode', true);
        }

        // In production, would redirect to actual bank OAuth
        return response()->json([
            'message'   => 'Production OpenBanking integration requires bank API setup',
            'demo_hint' => 'Enable DEMO_MODE to test this feature',
        ], 501);
    }

    public function callback(Request $request)
    {
        // In demo environment or sandbox mode, process the simulated deposit
        if (app()->environment('demo') || config('demo.sandbox.enabled')) {
            $depositData = session()->pull('openbanking_deposit');

            if (! $depositData) {
                return redirect()->route('wallet.deposit')
                    ->with('error', 'No pending OpenBanking deposit found.');
            }

            try {
                // Process the deposit through the payment service
                $reference = 'OB-DEP-' . strtoupper(uniqid());
                $this->paymentService->processOpenBankingDeposit([
                    'account_uuid' => $depositData['account_uuid'],
                    'amount'       => $depositData['amount'],
                    'currency'     => $depositData['currency'],
                    'reference'    => $reference,
                    'bank_name'    => $depositData['bank'],
                    'metadata'     => [
                        'environment'   => app()->environment('demo') ? 'demo' : 'sandbox',
                        'processor'     => 'openbanking_simulation',
                        'bank'          => $depositData['bank'],
                        'authorized_at' => now()->toIso8601String(),
                    ],
                ]);

                Log::info('OpenBanking deposit processed successfully', [
                    'reference' => $reference,
                    'amount'    => $depositData['amount'],
                    'bank'      => $depositData['bank'],
                ]);

                return redirect()->route('wallet.index')
                    ->with('success', sprintf(
                        'OpenBanking deposit successful! %s %s has been credited from %s.',
                        $depositData['currency'],
                        number_format($depositData['amount'] / 100, 2),
                        $depositData['bank']
                    ));
            } catch (\Exception $e) {
                Log::error('OpenBanking deposit failed', [
                    'error'        => $e->getMessage(),
                    'deposit_data' => $depositData,
                ]);

                return redirect()->route('wallet.deposit')
                    ->with('error', 'Failed to process OpenBanking deposit: ' . $e->getMessage());
            }
        }

        // In production, would handle actual bank OAuth callback
        return response()->json([
            'message'   => 'Production OpenBanking callback requires bank API setup',
            'demo_hint' => 'Enable DEMO_MODE to test this feature',
        ], 501);
    }
}
