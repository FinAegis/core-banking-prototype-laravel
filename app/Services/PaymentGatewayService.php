<?php

namespace App\Services;

use App\Models\User;
use App\Models\Account;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Banking\Models\PaymentMethod;
use App\Domain\Banking\Events\DepositCompleted;
use App\Domain\Banking\Events\WithdrawalRequested;
use App\Domain\Account\Services\AccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod as StripePaymentMethod;
use Exception;

class PaymentGatewayService
{
    protected AccountService $accountService;
    
    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }
    
    /**
     * Create a payment intent for deposit
     */
    public function createDepositIntent(User $user, int $amountInCents, string $currency = 'USD'): PaymentIntent
    {
        try {
            // Ensure user has a Stripe customer
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }
            
            // Create payment intent
            $intent = $user->pay($amountInCents, [
                'currency' => strtolower($currency),
                'metadata' => [
                    'user_id' => $user->id,
                    'type' => 'deposit',
                    'account_uuid' => $user->accounts()->first()->uuid ?? null,
                ],
                'description' => "Deposit to FinAegis account",
                'setup_future_usage' => 'on_session',
            ]);
            
            return $intent->asStripePaymentIntent();
        } catch (Exception $e) {
            Log::error('Failed to create deposit payment intent', [
                'user_id' => $user->id,
                'amount' => $amountInCents,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Process a successful deposit
     */
    public function processDeposit(string $paymentIntentId): Transaction
    {
        return DB::transaction(function () use ($paymentIntentId) {
            // Retrieve payment intent from Stripe
            $stripe = new \Stripe\StripeClient(config('cashier.secret'));
            $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId);
            
            if ($paymentIntent->status !== 'succeeded') {
                throw new Exception('Payment intent not succeeded');
            }
            
            // Find user and account
            $userId = $paymentIntent->metadata['user_id'] ?? null;
            $accountUuid = $paymentIntent->metadata['account_uuid'] ?? null;
            
            if (!$userId || !$accountUuid) {
                throw new Exception('Invalid payment metadata');
            }
            
            $account = Account::where('uuid', $accountUuid)->firstOrFail();
            
            // Create transaction record
            $transaction = Transaction::create([
                'account_id' => $account->id,
                'type' => 'deposit',
                'amount' => $paymentIntent->amount,
                'currency' => strtoupper($paymentIntent->currency),
                'status' => 'completed',
                'reference' => 'DEP-' . strtoupper(uniqid()),
                'external_reference' => $paymentIntent->id,
                'processor' => 'stripe',
                'metadata' => [
                    'payment_method' => $paymentIntent->payment_method,
                    'payment_method_type' => $paymentIntent->payment_method_types[0] ?? 'card',
                    'stripe_payment_intent_id' => $paymentIntent->id,
                ],
                'processed_at' => now(),
            ]);
            
            // Update account balance using AccountService
            $this->accountService->deposit($account->uuid, [
                'amount' => $paymentIntent->amount,
                'currency' => strtoupper($paymentIntent->currency)
            ]);
            
            // Dispatch event
            event(new DepositCompleted($transaction));
            
            return $transaction;
        });
    }
    
    /**
     * Create a bank withdrawal request
     */
    public function createWithdrawalRequest(
        Account $account, 
        int $amountInCents, 
        string $currency,
        array $bankDetails
    ): Transaction {
        return DB::transaction(function () use ($account, $amountInCents, $currency, $bankDetails) {
            // Validate account has sufficient balance
            $balance = $account->getBalance($currency);
            if ($balance < $amountInCents) {
                throw new Exception('Insufficient balance');
            }
            
            // Create pending withdrawal transaction
            $transaction = Transaction::create([
                'account_id' => $account->id,
                'type' => 'withdrawal',
                'amount' => $amountInCents,
                'currency' => $currency,
                'status' => 'pending',
                'reference' => 'WTH-' . strtoupper(uniqid()),
                'processor' => 'bank_transfer',
                'metadata' => [
                    'bank_name' => $bankDetails['bank_name'],
                    'account_number' => $bankDetails['account_number'],
                    'routing_number' => $bankDetails['routing_number'] ?? null,
                    'iban' => $bankDetails['iban'] ?? null,
                    'swift' => $bankDetails['swift'] ?? null,
                    'account_holder_name' => $bankDetails['account_holder_name'],
                ],
            ]);
            
            // Debit account (hold funds) using AccountService
            $this->accountService->withdraw($account->uuid, [
                'amount' => $amountInCents,
                'currency' => $currency
            ]);
            
            // Dispatch event for processing
            event(new WithdrawalRequested($transaction));
            
            return $transaction;
        });
    }
    
    /**
     * Get saved payment methods for a user
     */
    public function getSavedPaymentMethods(User $user): array
    {
        if (!$user->hasStripeId()) {
            return [];
        }
        
        try {
            $methods = $user->paymentMethods();
            
            return $methods->map(function ($method) {
                return [
                    'id' => $method->id,
                    'brand' => $method->card->brand,
                    'last4' => $method->card->last4,
                    'exp_month' => $method->card->exp_month,
                    'exp_year' => $method->card->exp_year,
                    'is_default' => $method->id === optional($this->defaultPaymentMethod())->id,
                ];
            })->toArray();
        } catch (Exception $e) {
            Log::error('Failed to fetch payment methods', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * Add a new payment method
     */
    public function addPaymentMethod(User $user, string $paymentMethodId): StripePaymentMethod
    {
        try {
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }
            
            return $user->addPaymentMethod($paymentMethodId);
        } catch (Exception $e) {
            Log::error('Failed to add payment method', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Remove a payment method
     */
    public function removePaymentMethod(User $user, string $paymentMethodId): void
    {
        try {
            $paymentMethod = $user->findPaymentMethod($paymentMethodId);
            if ($paymentMethod) {
                $paymentMethod->delete();
            }
        } catch (Exception $e) {
            Log::error('Failed to remove payment method', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}