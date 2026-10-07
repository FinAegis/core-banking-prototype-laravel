<?php

namespace App\Domain\Payment\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Exceptions\DepositAlreadyProcessedException;
use App\Domain\Payment\Exceptions\DepositNotAllowedException;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\PaymentMethod as CashierPaymentMethod;
use Stripe\PaymentIntent;
use Throwable;

class PaymentGatewayService
{
    protected PaymentServiceInterface $paymentService;

    protected DepositReplayGuard $replayGuard;

    public function __construct(PaymentServiceInterface $paymentService, ?DepositReplayGuard $replayGuard = null)
    {
        $this->paymentService = $paymentService;
        $this->replayGuard = $replayGuard ?? new DepositReplayGuard();
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
     * Credit a succeeded Stripe PaymentIntent to the caller's account.
     *
     * The PaymentIntent must have been created for $user (metadata user_id)
     * and target an account $user owns (metadata account_uuid). Each
     * PaymentIntent is credited at most once.
     *
     * @throws DepositNotAllowedException when the PaymentIntent does not belong to $user
     * @throws DepositAlreadyProcessedException when the PaymentIntent was already credited
     */
    public function processDeposit(string $paymentIntentId, User $user): array
    {
        $paymentIntent = $this->retrievePaymentIntent($paymentIntentId);

        if ($paymentIntent->status !== 'succeeded') {
            throw new Exception('Payment intent not succeeded');
        }

        // Find user and account
        $userId = $paymentIntent->metadata['user_id'] ?? null;
        $accountUuid = $paymentIntent->metadata['account_uuid'] ?? null;

        if (! $userId || ! $accountUuid) {
            throw new Exception('Invalid payment metadata');
        }

        // Only PaymentIntents created by createDepositIntent() are deposits.
        if (($paymentIntent->metadata['type'] ?? null) !== 'deposit') {
            throw new DepositNotAllowedException('This payment is not a deposit.');
        }

        $this->assertDepositBelongsTo($user, (string) $userId, (string) $accountUuid, $paymentIntent->id);

        if (! $this->replayGuard->claim(DepositReplayGuard::SOURCE_STRIPE, $paymentIntent->id)) {
            throw new DepositAlreadyProcessedException(DepositReplayGuard::SOURCE_STRIPE, $paymentIntent->id);
        }

        // Use PaymentService to process deposit through event sourcing
        $reference = 'DEP-' . strtoupper(uniqid());

        try {
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
        } catch (Throwable $e) {
            // Nothing was credited: give the PaymentIntent back so it can be retried.
            $this->replayGuard->release(DepositReplayGuard::SOURCE_STRIPE, $paymentIntent->id);

            throw $e;
        }

        return [
            'account_uuid' => $accountUuid,
            'amount'       => $paymentIntent->amount,
            'currency'     => strtoupper($paymentIntent->currency),
            'reference'    => $reference,
        ];
    }

    /**
     * Retrieve a PaymentIntent from Stripe.
     */
    protected function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
    {
        $stripe = new \Stripe\StripeClient(config('cashier.secret'));

        return $stripe->paymentIntents->retrieve($paymentIntentId);
    }

    /**
     * The deposit must have been started by $user for an account $user owns.
     *
     * @throws DepositNotAllowedException
     */
    protected function assertDepositBelongsTo(User $user, string $intentUserId, string $accountUuid, string $externalReference): void
    {
        if ($intentUserId !== (string) $user->getKey() || Account::findOwnedBy($accountUuid, $user) === null) {
            Log::warning('Deposit confirmation refused: payment does not belong to the authenticated user', [
                'user_id'            => $user->getKey(),
                'external_reference' => $externalReference,
            ]);

            throw new DepositNotAllowedException('This payment does not belong to your account.');
        }
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
