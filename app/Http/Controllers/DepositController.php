<?php

namespace App\Http\Controllers;

use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Services\PaymentGatewayService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepositController extends Controller
{
    protected PaymentGatewayService $paymentGateway;

    protected PaymentServiceInterface $paymentService;

    public function __construct(PaymentGatewayService $paymentGateway, PaymentServiceInterface $paymentService)
    {
        $this->paymentGateway = $paymentGateway;
        $this->paymentService = $paymentService;
    }

    /**
     * Show the deposit form.
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

        // Get saved payment methods
        $paymentMethods = $this->paymentGateway->getSavedPaymentMethods($user);

        return view(
            'wallet.deposit-card',
            [
                'account'        => $account,
                'paymentMethods' => $paymentMethods,
                'stripeKey'      => config('cashier.key'),
            ]
        );
    }

    /**
     * Create a payment intent for deposit.
     */
    public function store(Request $request)
    {
        $request->validate(
            [
                'amount'   => 'required|numeric|min:1|max:10000',
                'currency' => 'required|in:USD,EUR,GBP',
            ]
        );

        $user = Auth::user();
        /** @var User $user */
        $amountInCents = (int) ($request->amount * 100);

        try {
            $intent = $this->paymentGateway->createDepositIntent(
                $user,
                $amountInCents,
                $request->currency
            );

            return response()->json(
                [
                    'client_secret' => $intent->client_secret,
                    'amount'        => $amountInCents,
                    'currency'      => $request->currency,
                ]
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'error' => 'Failed to create payment intent. Please try again.',
                ],
                500
            );
        }
    }

    /**
     * Confirm a successful deposit.
     */
    public function confirm(Request $request)
    {
        $request->validate(
            [
                'payment_intent_id' => 'required|string',
            ]
        );

        try {
            $result = $this->paymentGateway->processDeposit($request->payment_intent_id);

            return redirect()->route('wallet.index')
                ->with('success', 'Deposit successful! Your account has been credited.');
        } catch (\Exception $e) {
            return redirect()->route('wallet.deposit')
                ->with('error', 'Failed to process deposit. Please contact support.');
        }
    }

    /**
     * Process a demo/simulated deposit without external payment processor.
     * This is used in demo mode to demonstrate platform functionality.
     */
    public function simulateDeposit(Request $request)
    {
        // Only allow in demo or sandbox mode
        if (! config('demo.mode') && ! config('demo.sandbox.enabled')) {
            abort(403, 'Simulated deposits are only available in demo mode.');
        }

        $request->validate([
            'amount'   => 'required|numeric|min:1|max:10000',
            'currency' => 'required|in:USD,EUR,GBP',
        ]);

        $user = Auth::user();
        /** @var User $user */
        $account = $user->accounts()->first();

        if (! $account) {
            return redirect()->route('wallet.deposit')
                ->with('error', 'Please create an account first.');
        }

        try {
            // Create a simulated payment intent
            $amountInCents = (int) ($request->amount * 100);
            $intent = $this->paymentGateway->createDepositIntent(
                $user,
                $amountInCents,
                $request->currency
            );

            // Immediately process it (bypassing Stripe redirect)
            $result = $this->paymentGateway->processDeposit($intent->id);

            return redirect()->route('wallet.index')
                ->with('success', 'Demo deposit successful! Your account has been credited with ' .
                    $request->currency . ' ' . number_format($request->amount, 2));
        } catch (\Exception $e) {
            \Log::error('Demo deposit failed', [
                'error'   => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return redirect()->route('wallet.deposit')
                ->with('error', 'Failed to process demo deposit: ' . $e->getMessage());
        }
    }

    /**
     * Add a new payment method.
     */
    public function addPaymentMethod(Request $request)
    {
        $request->validate(
            [
                'payment_method_id' => 'required|string',
            ]
        );

        try {
            $this->paymentGateway->addPaymentMethod(
                Auth::user(),
                $request->payment_method_id
            );

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Payment method added successfully.',
                ]
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'error' => 'Failed to add payment method.',
                ],
                500
            );
        }
    }

    /**
     * Remove a payment method.
     */
    public function removePaymentMethod(Request $request, string $paymentMethodId)
    {
        try {
            $this->paymentGateway->removePaymentMethod(
                Auth::user(),
                $paymentMethodId
            );

            return redirect()->back()
                ->with('success', 'Payment method removed successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to remove payment method.');
        }
    }
}
