<?php

namespace App\Domain\Payment\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\PaymentMethod as CashierPaymentMethod;
use Stripe\PaymentIntent;

class PaymentGatewayService
{
    protected PaymentServiceInterface $paymentService;

    public function __construct(PaymentServiceInterface $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Create a payment intent for deposit.
     */
    public function createDepositIntent(User $user, int $amountInCents, string $currency = 'USD'): PaymentIntent
    {
        try {
            // Ensure user has a Stripe customer
            if (! $user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            // Create payment intent
            $intent = $user->pay(
                $amountInCents,
                [
                    'currency' => strtolower($currency),
                    'metadata' => [
                        'user_id'      => $user->id,
                        'type'         => 'deposit',
                        'account_uuid' => $user->accounts()->first()->uuid ?? null,
                    ],
                    'description'        => 'Deposit to FinAegis account',
                    'setup_future_usage' => 'on_session',
                ]
            );

            return $intent->asStripePaymentIntent();
        } catch (Exception $e) {
            Log::error(
                'Failed to create deposit payment intent',
                [
                    'user_id' => $user->id,
                    'amount'  => $amountInCents,
                    'error'   => $e->getMessage(),
                ]
            );
            throw $e;
        }
    }

    /**
     * Process a successful deposit.
     */
    public function processDeposit(string $paymentIntentId): array
    {
        // Retrieve payment intent from Stripe
        $stripe = new \Stripe\StripeClient(config('cashier.secret'));
        $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId);

        if ($paymentIntent->status !== 'succeeded') {
            throw new Exception('Payment intent not succeeded');
        }

        // Find user and account
        $userId = $paymentIntent->metadata['user_id'] ?? null;
        $accountUuid = $paymentIntent->metadata['account_uuid'] ?? null;

        if (! $userId || ! $accountUuid) {
            throw new Exception('Invalid payment metadata');
        }

        // Use PaymentService to process deposit through event sourcing
        $reference = 'DEP-' . strtoupper(uniqid());
        $this->paymentService->processStripeDeposit(
            [
                'account_uuid'        => $accountUuid,
                'amount'              => $paymentIntent->amount,
                'currency'            => strtoupper($paymentIntent->currency),
                'reference'           => $reference,
                'external_reference'  => $paymentIntent->id,
                'payment_method'      => $paymentIntent->payment_method,
                'payment_method_type' => $paymentIntent->payment_method_types[0] ?? 'card',
                'metadata'            => [
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'processor'                => 'stripe',
                ],
            ]
        );

        return [
            'account_uuid' => $accountUuid,
            'amount'       => $paymentIntent->amount,
            'currency'     => strtoupper($paymentIntent->currency),
            'reference'    => 'DEP-' . strtoupper(uniqid()),
        ];
    }

    /**
     * Create a bank withdrawal request.
     */
    public function createWithdrawalRequest(
        Account $account,
        int $amountInCents,
        string $currency,
        array $bankDetails
    ): array {
        // Validate account has sufficient balance
        $balance = $account->getBalance($currency);
        if ($balance < $amountInCents) {
            throw new Exception('Insufficient balance');
        }

        $reference = 'WTH-' . strtoupper(uniqid());

        // Use PaymentService to process withdrawal through event sourcing
        $result = $this->paymentService->processBankWithdrawal(
            [
                'account_uuid'        => $account->uuid,
                'amount'              => $amountInCents,
                'currency'            => $currency,
                'reference'           => $reference,
                'bank_name'           => $bankDetails['bank_name'],
                'account_number'      => $bankDetails['account_number'],
                'account_holder_name' => $bankDetails['account_holder_name'],
                'routing_number'      => $bankDetails['routing_number'] ?? null,
                'iban'                => $bankDetails['iban'] ?? null,
                'swift'               => $bankDetails['swift'] ?? null,
                'metadata'            => [
                    'processor'    => 'bank_transfer',
                    'initiated_at' => now()->toIso8601String(),
                ],
            ]
        );

        return [
            'account_uuid' => $account->uuid,
            'amount'       => $amountInCents,
            'currency'     => $currency,
            'reference'    => $reference,
        ];
    }

    /**
     * Get saved payment methods for a user.
     */
    public function getSavedPaymentMethods(User $user): array
    {
        if (! $user->hasStripeId()) {
            return [];
        }

        try {
            $methods = $user->paymentMethods();

            return $methods->map(
                function ($method) {
                    return [
                        'id'         => $method->id,
                        'brand'      => $method->card->brand,
                        'last4'      => $method->card->last4,
                        'exp_month'  => $method->card->exp_month,
                        'exp_year'   => $method->card->exp_year,
                        'is_default' => $method->id === optional($this->defaultPaymentMethod())->id,
                    ];
                }
            )->toArray();
        } catch (Exception $e) {
            Log::error(
                'Failed to fetch payment methods',
                [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]
            );

            return [];
        }
    }

    /**
     * Add a new payment method.
     */
    public function addPaymentMethod(User $user, string $paymentMethodId): CashierPaymentMethod
    {
        try {
            if (! $user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            return $user->addPaymentMethod($paymentMethodId);
        } catch (Exception $e) {
            Log::error(
                'Failed to add payment method',
                [
                    'user_id'           => $user->id,
                    'payment_method_id' => $paymentMethodId,
                    'error'             => $e->getMessage(),
                ]
            );
            throw $e;
        }
    }

    /**
     * Remove a payment method.
     */
    public function removePaymentMethod(User $user, string $paymentMethodId): void
    {
        try {
            $paymentMethod = $user->findPaymentMethod($paymentMethodId);
            if ($paymentMethod) {
                $paymentMethod->delete();
            }
        } catch (Exception $e) {
            Log::error(
                'Failed to remove payment method',
                [
                    'user_id'           => $user->id,
                    'payment_method_id' => $paymentMethodId,
                    'error'             => $e->getMessage(),
                ]
            );
            throw $e;
        }
    }
}
