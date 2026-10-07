<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Exceptions\DepositAlreadyProcessedException;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\PaymentMethod as CashierPaymentMethod;
use RuntimeException;
use Stripe\PaymentIntent;
use Throwable;

/**
 * Demo Payment Gateway Service that simulates Stripe operations without external API calls.
 * Extends the production service to maintain compatibility while bypassing Stripe.
 */
class DemoPaymentGatewayService extends PaymentGatewayService
{
    /**
     * Store for demo payment intents to simulate real behavior.
     */
    private static array $demoIntents = [];

    public function __construct(PaymentServiceInterface $paymentService)
    {
        parent::__construct($paymentService);

        if (app()->environment('production')) {
            throw new RuntimeException(static::class . ' cannot be used in production');
        }
    }

    /**
     * Create a demo payment intent without calling Stripe.
     */
    public function createDepositIntent(User $user, int $amountInCents, string $currency = 'USD'): PaymentIntent
    {
        Log::info('Creating demo payment intent', [
            'user_id'  => $user->id,
            'amount'   => $amountInCents,
            'currency' => $currency,
        ]);

        // Create a mock PaymentIntent using array data since we can't set properties directly
        $intentId = 'demo_pi_' . uniqid();
        $intentData = [
            'id'            => $intentId,
            'object'        => 'payment_intent',
            'amount'        => $amountInCents,
            'currency'      => strtolower($currency),
            'status'        => 'requires_payment_method',
            'client_secret' => 'demo_secret_' . uniqid(),
            'created'       => time(),
            'metadata'      => [
                'user_id'      => $user->id,
                'type'         => 'deposit',
                'account_uuid' => $user->accounts()->first()->uuid ?? null,
                'demo_mode'    => true,
            ],
            'description' => 'Demo deposit to FinAegis account',
        ];

        // Store intent data for later retrieval
        self::$demoIntents[$intentId] = [
            'user_id'      => $user->id,
            'account_uuid' => $user->accounts()->first()->uuid ?? null,
            'amount'       => $amountInCents,
            'currency'     => $currency,
        ];

        // Create PaymentIntent from array (simulating Stripe response)
        return PaymentIntent::constructFrom($intentData);
    }

    /**
     * Process a demo deposit without calling Stripe.
     *
     * Same rules as the real gateway: the intent must belong to $user and
     * target an account $user owns, and each intent id is credited once.
     */
    public function processDeposit(string $paymentIntentId, User $user): array
    {
        Log::info('Processing demo deposit', [
            'payment_intent_id' => $paymentIntentId,
        ]);

        $reference = 'DEP-' . strtoupper(uniqid());

        // Simulate the deposit processing
        try {
            // Retrieve intent data from our store (same request) or fall back
            // to a default demo amount on the caller's own first account.
            $intentData = self::$demoIntents[$paymentIntentId] ?? null;

            if (! $intentData) {
                $account = $user->accounts()->first();
                if (! $account) {
                    throw new Exception('No accounts found for demo deposit');
                }

                $intentData = [
                    'user_id'      => $user->getKey(),
                    'account_uuid' => $account->uuid,
                    'amount'       => 10000, // Default to $100
                    'currency'     => 'USD',
                ];
            }

            $this->assertDepositBelongsTo(
                $user,
                (string) ($intentData['user_id'] ?? ''),
                (string) ($intentData['account_uuid'] ?? ''),
                $paymentIntentId,
            );

            if (! $this->replayGuard->claim(DepositReplayGuard::SOURCE_STRIPE, $paymentIntentId)) {
                throw new DepositAlreadyProcessedException(DepositReplayGuard::SOURCE_STRIPE, $paymentIntentId);
            }

            // Process through the payment service
            try {
                $this->paymentService->processStripeDeposit([
                    'account_uuid'        => $intentData['account_uuid'],
                    'amount'              => $intentData['amount'],
                    'currency'            => $intentData['currency'],
                    'reference'           => $reference,
                    'external_reference'  => $paymentIntentId,
                    'payment_method'      => 'demo_card',
                    'payment_method_type' => 'card',
                    'metadata'            => [
                        'demo_mode'         => true,
                        'payment_intent_id' => $paymentIntentId,
                    ],
                ]);
            } catch (Throwable $e) {
                $this->replayGuard->release(DepositReplayGuard::SOURCE_STRIPE, $paymentIntentId);

                throw $e;
            }

            return [
                'reference' => $reference,
                'status'    => 'succeeded',
            ];
        } catch (Exception $e) {
            Log::error('Demo deposit failed', [
                'error'             => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
            ]);
            throw $e;
        }
    }

    /**
     * Get saved payment methods (returns demo methods).
     */
    public function getSavedPaymentMethods(User $user): array
    {
        Log::info('Getting demo payment methods', [
            'user_id' => $user->id,
        ]);

        // Return demo payment methods matching the real PaymentGatewayService format
        return [
            [
                'id'         => 'demo_pm_card',
                'brand'      => 'visa',
                'last4'      => '4242',
                'exp_month'  => 12,
                'exp_year'   => date('Y') + 1,
                'is_default' => true,
            ],
            [
                'id'         => 'demo_pm_card_2',
                'brand'      => 'mastercard',
                'last4'      => '5555',
                'exp_month'  => 6,
                'exp_year'   => date('Y') + 2,
                'is_default' => false,
            ],
        ];
    }

    /**
     * Add a demo payment method.
     */
    public function addPaymentMethod(User $user, string $paymentMethodId): CashierPaymentMethod
    {
        Log::info('Adding demo payment method', [
            'user_id'           => $user->id,
            'payment_method_id' => $paymentMethodId,
        ]);

        // Create a mock Stripe PaymentMethod using constructFrom
        $paymentMethodData = [
            'id'   => $paymentMethodId,
            'type' => 'card',
            'card' => [
                'brand'     => 'visa',
                'last4'     => substr($paymentMethodId, -4),
                'exp_month' => 12,
                'exp_year'  => date('Y') + 1,
            ],
            'created'  => time(),
            'customer' => $user->stripe_id ?: 'cus_demo_' . $user->id, // Required by CashierPaymentMethod
        ];

        $stripePaymentMethod = \Stripe\PaymentMethod::constructFrom($paymentMethodData);

        return new CashierPaymentMethod($user, $stripePaymentMethod);
    }

    /**
     * Remove a demo payment method.
     */
    public function removePaymentMethod(User $user, string $paymentMethodId): void
    {
        Log::info('Removing demo payment method', [
            'user_id'           => $user->id,
            'payment_method_id' => $paymentMethodId,
        ]);

        // Always succeed in demo mode (no-op)
    }
}
