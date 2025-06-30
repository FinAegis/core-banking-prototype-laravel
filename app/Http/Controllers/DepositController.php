<?php

namespace App\Http\Controllers;

use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DepositController extends Controller
{
    protected PaymentGatewayService $paymentGateway;
    
    public function __construct(PaymentGatewayService $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }
    
    /**
     * Show the deposit form
     */
    public function create()
    {
        $user = Auth::user();
        $account = $user->accounts()->first();
        
        if (!$account) {
            return redirect()->route('dashboard')
                ->with('error', 'Please create an account first.');
        }
        
        // Get saved payment methods
        $paymentMethods = $this->paymentGateway->getSavedPaymentMethods($user);
        
        return view('wallet.deposit-card', [
            'account' => $account,
            'paymentMethods' => $paymentMethods,
            'stripeKey' => config('cashier.key'),
        ]);
    }
    
    /**
     * Create a payment intent for deposit
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:10000',
            'currency' => 'required|in:USD,EUR,GBP',
        ]);
        
        $user = Auth::user();
        $amountInCents = (int) ($request->amount * 100);
        
        try {
            $intent = $this->paymentGateway->createDepositIntent(
                $user,
                $amountInCents,
                $request->currency
            );
            
            return response()->json([
                'client_secret' => $intent->client_secret,
                'amount' => $amountInCents,
                'currency' => $request->currency,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create payment intent. Please try again.',
            ], 500);
        }
    }
    
    /**
     * Confirm a successful deposit
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);
        
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
     * Add a new payment method
     */
    public function addPaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);
        
        try {
            $this->paymentGateway->addPaymentMethod(
                Auth::user(),
                $request->payment_method_id
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Payment method added successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to add payment method.',
            ], 500);
        }
    }
    
    /**
     * Remove a payment method
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