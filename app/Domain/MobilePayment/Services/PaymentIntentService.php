<?php

declare(strict_types=1);

namespace App\Domain\MobilePayment\Services;

use App\Domain\MobilePayment\Contracts\MerchantLookupServiceInterface;
use App\Domain\MobilePayment\Contracts\PaymentIntentServiceInterface;
use App\Domain\MobilePayment\Enums\PaymentErrorCode;
use App\Domain\MobilePayment\Enums\PaymentIntentStatus;
use App\Domain\MobilePayment\Enums\PaymentNetwork;
use App\Domain\MobilePayment\Exceptions\PaymentIntentException;
use App\Domain\MobilePayment\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentIntentService implements PaymentIntentServiceInterface
{
    public function __construct(
        private readonly MerchantLookupServiceInterface $merchantLookup,
        private readonly FeeEstimationService $feeEstimation,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $userId, array $data): PaymentIntent
    {
        $merchantId = (string) $data['merchantId'];
        $asset = (string) $data['asset'];
        $network = (string) $data['preferredNetwork'];
        $amount = (string) $data['amount'];
        $shield = (bool) ($data['shield'] ?? false);
        $idempotencyKey = isset($data['idempotencyKey']) ? (string) $data['idempotencyKey'] : null;

        // Enforce idempotency: return existing intent if key matches
        if ($idempotencyKey !== null) {
            $existing = PaymentIntent::where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                $existing->load('merchant');

                return $existing;
            }
        }

        // Look up merchant
        $merchant = $this->merchantLookup->findByPublicId($merchantId);

        // Verify merchant can accept payments
        if (! $merchant->canAcceptPayments()) {
            throw new PaymentIntentException(
                PaymentErrorCode::MERCHANT_UNREACHABLE,
            );
        }

        // Validate merchant accepts this asset+network
        if (! $this->merchantLookup->acceptsPayment($merchant, $asset, $network)) {
            if (! $merchant->acceptsAsset($asset)) {
                throw new PaymentIntentException(
                    PaymentErrorCode::WRONG_TOKEN,
                );
            }

            throw new PaymentIntentException(
                PaymentErrorCode::WRONG_NETWORK,
            );
        }

        // Estimate fees
        $networkEnum = PaymentNetwork::from($network);
        $fees = $this->feeEstimation->estimate($networkEnum, $amount, $shield);

        // Create the intent
        $expiryMinutes = (int) config('mobile_payment.expiry_minutes', 15);

        $intent = PaymentIntent::create([
            'public_id'              => 'pi_' . Str::random(20),
            'user_id'                => $userId,
            'merchant_id'            => $merchant->id,
            'asset'                  => $asset,
            'network'                => $network,
            'amount'                 => $amount,
            'status'                 => PaymentIntentStatus::CREATED,
            'shield_enabled'         => $shield,
            'fees_estimate'          => $fees->toArray(),
            'required_confirmations' => $networkEnum->requiredConfirmations(),
            'idempotency_key'        => $idempotencyKey,
            'expires_at'             => now()->addMinutes($expiryMinutes),
        ]);

        $intent->setRelation('merchant', $merchant);

        // Transition through the state machine to AWAITING_AUTH
        $intent->transitionTo(PaymentIntentStatus::AWAITING_AUTH);

        return $intent;
    }

    public function get(string $intentId, int $userId): PaymentIntent
    {
        $intent = PaymentIntent::with('merchant')
            ->where('public_id', $intentId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Lazy expiry check
        $intent->expireIfStale();

        return $intent;
    }

    public function submit(string $intentId, int $userId, string $authType): PaymentIntent
    {
        return DB::transaction(function () use ($intentId, $userId) {
            $intent = PaymentIntent::with('merchant')
                ->where('public_id', $intentId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            // Lazy expiry check
            $intent->expireIfStale();

            // Check if already submitted
            if (in_array($intent->status, [PaymentIntentStatus::SUBMITTING, PaymentIntentStatus::PENDING], true)) {
                throw new PaymentIntentException(
                    PaymentErrorCode::INTENT_ALREADY_SUBMITTED,
                );
            }

            // Check if expired
            if ($intent->status === PaymentIntentStatus::EXPIRED) {
                throw new PaymentIntentException(
                    PaymentErrorCode::INTENT_EXPIRED,
                );
            }

            // Transition to SUBMITTING
            $intent->transitionTo(PaymentIntentStatus::SUBMITTING);

            // In demo mode, simulate immediate submission success
            if (config('mobile_payment.demo_mode', true)) {
                $this->simulateDemoSubmission($intent);
            }

            return $intent->fresh(['merchant']) ?? $intent;
        });
    }

    public function cancel(string $intentId, int $userId, ?string $reason = null): PaymentIntent
    {
        $intent = $this->get($intentId, $userId);

        if (! $intent->status->isCancellable()) {
            $errorCode = match (true) {
                $intent->status === PaymentIntentStatus::EXPIRED                                                 => PaymentErrorCode::INTENT_EXPIRED,
                in_array($intent->status, [PaymentIntentStatus::SUBMITTING, PaymentIntentStatus::PENDING], true) => PaymentErrorCode::INTENT_ALREADY_SUBMITTED,
                default                                                                                          => PaymentErrorCode::INTENT_ALREADY_SUBMITTED,
            };

            throw new PaymentIntentException($errorCode);
        }

        // Set cancel_reason before transitionTo; transitionTo calls save() atomically
        $intent->cancel_reason = $reason ?? 'user_cancelled';
        $intent->transitionTo(PaymentIntentStatus::CANCELLED);

        return $intent->fresh(['merchant']) ?? $intent;
    }

    /**
     * Simulate a successful demo payment submission.
     */
    private function simulateDemoSubmission(PaymentIntent $intent): void
    {
        $network = PaymentNetwork::from($intent->network);
        $demoTxHash = $this->generateDemoTxHash($network);

        $intent->tx_hash = $demoTxHash;
        $intent->tx_explorer_url = $network->explorerUrl($demoTxHash);
        $intent->confirmations = 0;
        $intent->save();

        $intent->transitionTo(PaymentIntentStatus::PENDING);
    }

    private function generateDemoTxHash(PaymentNetwork $network): string
    {
        return match ($network) {
            PaymentNetwork::SOLANA                                                                            => Str::random(88),
            PaymentNetwork::TRON                                                                              => Str::random(64),
            PaymentNetwork::POLYGON, PaymentNetwork::BASE, PaymentNetwork::ARBITRUM, PaymentNetwork::ETHEREUM => '0x' . bin2hex(random_bytes(32)),
        };
    }
}
