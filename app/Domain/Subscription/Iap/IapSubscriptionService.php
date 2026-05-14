<?php

/**
 * IapSubscriptionService — Plan B Slice 2 IAP path.
 *
 * Owns the custom (non-Cashier) Apple App Store + Google Play subscription
 * flow per Backend-Q1 γ. Separate from the Stripe-only SubscriptionService —
 * the two paths are deliberately isolated so neither becomes a god-service.
 *
 * Endpoints powered: POST /api/v1/subscription/iap/verify
 *
 * Returns the same `['code' => ...]` / `['success' => ...]` shape as
 * SubscriptionService so the controller can render uniformly via
 * ErrorResponse::make().
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.1 / §5.7
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Iap;

use App\Domain\Subscription\Models\IapReceipt;
use App\Domain\Subscription\Models\IapSubscription;
use App\Domain\Subscription\Models\IapSubscriptionEvent;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use App\Domain\Subscription\Models\SubscriptionConsentLog;
use App\Domain\Subscription\Projections\SubscriptionProjection;
use App\Domain\Subscription\Services\ConsentLogWriter;
use App\Domain\Subscription\Services\SubscriptionService;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class IapSubscriptionService
{
    public function __construct(
        private readonly AppleReceiptVerifier $apple,
        private readonly GooglePlayReceiptVerifier $google,
        private readonly SubscriptionProjection $projection,
        private readonly SubscriptionService $subscriptionService,
        private readonly ConsentLogWriter $consent,
    ) {
    }

    /**
     * POST /api/v1/subscription/iap/verify — implements the §5.1 processing
     * sequence end to end. Returns the projection shape on 2xx, or an error
     * code envelope on rejection.
     *
     * @param array{
     *     platform: string,
     *     receipt: string,
     *     originalTransactionId?: string|null,
     *     productId: string,
     *     appVersion?: string,
     *     currency?: string,
     *     withdrawalConsent?: array{
     *         given: bool, shownAt: string, acceptedAt: string,
     *         consentText: string, version: int
     *     }|null
     * } $input
     *
     * @return array{code?: string, context?: array<string, mixed>, success?: array<string, mixed>}
     */
    public function verify(User $user, array $input, ?string $remoteIp = null, ?string $userAgent = null): array
    {
        $platform = $input['platform'];
        $productId = $input['productId'];

        // 1. Currency gate (ADR-0004, v1.3.x EUR-only).
        $currency = strtoupper((string) ($input['currency'] ?? 'EUR'));
        if ($currency !== 'EUR') {
            return ['code' => 'ERR_CUR_001'];
        }

        // 2. Plan resolution from the store product id (config map).
        $plan = $this->planForProductId(
            $platform === 'apple_iap' ? 'apple' : 'google',
            $productId,
        );
        if ($plan === null) {
            return [
                'code'    => 'ERR_SUB_001',
                'context' => ['detail' => "Unknown productId '{$productId}' for platform '{$platform}'."],
            ];
        }

        // 3. Verify receipt against the store.
        try {
            if ($platform === 'apple_iap') {
                $verified = $this->apple->verify(
                    (string) $input['receipt'],
                    (string) ($input['originalTransactionId'] ?? ''),
                );
                /** @var AppleVerifiedTransaction $verified */
            } else {
                $verified = $this->google->verify(
                    (string) $input['receipt'],
                    $productId,
                );
                /** @var GoogleVerifiedSubscription $verified */
            }
        } catch (IapVerificationException $e) {
            Log::info('iap.verify.receipt_rejected', [
                'platform' => $platform,
                'reason'   => $e->getMessage(),
                'user_id'  => $user->id,
            ]);

            return ['code' => 'ERR_SUB_001'];
        }

        // 4. Family Sharing rejection (Apple only — Google has no equivalent).
        if ($verified instanceof AppleVerifiedTransaction && $verified->isFamilyShared) {
            return ['code' => 'ERR_SUB_008'];
        }

        // 5. appAccountToken / obfuscatedAccountId binding check.
        if (! $this->matchesAuthenticatedUser($verified, $user)) {
            return ['code' => 'ERR_SUB_008'];
        }

        // 6. Multi-store conflict gate (deltas Q6).
        // Stripe conflict → ERR_SUB_002 kind=two_stores_active.
        if ($this->projection->hasActiveProSubscription($user, source: 'stripe')) {
            return [
                'code'    => 'ERR_SUB_002',
                'context' => [
                    'conflict' => [
                        'kind'                 => 'two_stores_active',
                        'existingSubscription' => $this->projection->for($user),
                        'attemptedSource'      => $platform,
                    ],
                ],
            ];
        }

        // Cross-store IAP conflict (apple<>google) → ERR_SUB_002.
        $opposingStore = $platform === 'apple_iap' ? 'google' : 'apple';
        if ($this->projection->hasActiveProSubscription($user, source: $opposingStore)) {
            return [
                'code'    => 'ERR_SUB_002',
                'context' => [
                    'conflict' => [
                        'kind'                 => 'two_stores_active',
                        'existingSubscription' => $this->projection->for($user),
                        'attemptedSource'      => $platform,
                    ],
                ],
            ];
        }

        // 7. Persist + outbox + consent in one transaction.
        try {
            /** @var array{code?: string, context?: array<string, mixed>, success?: array<string, mixed>} $result */
            $result = DB::transaction(function () use (
                $verified,
                $user,
                $plan,
                $productId,
                $input,
                $remoteIp,
                $userAgent
            ) {
                if ($verified instanceof AppleVerifiedTransaction) {
                    return $this->persistApple($verified, $user, $plan, $productId, $input, $remoteIp, $userAgent);
                }

                /** @var GoogleVerifiedSubscription $verified */
                return $this->persistGoogle($verified, $user, $plan, $productId, $input, $remoteIp, $userAgent);
            });
        } catch (QueryException $e) {
            // Unique-index collision against `uniq_iap_active_per_user`
            // indicates a cross-store race we couldn't catch above. Treat as
            // the same-store duplicate path: return 200 idempotent.
            if ($this->isDuplicateKeyError($e)) {
                Log::warning('iap.verify.duplicate_active_sub_race', [
                    'user_id'  => $user->id,
                    'platform' => $platform,
                    'error'    => $e->getMessage(),
                ]);

                return [
                    'success' => array_merge($this->projection->for($user->refresh()), [
                        'reactivated' => false,
                    ]),
                ];
            }

            throw $e;
        }

        return $result;
    }

    /**
     * Map a store product id → internal plan key (`monthly_pro` / `annual_pro`).
     * Returns null for an unknown product id.
     */
    public function planForProductId(string $store, string $productId): ?string
    {
        $map = (array) config("subscription.iap.{$store}.product_ids", []);

        foreach ($map as $plan => $configuredProductId) {
            if ((string) $configuredProductId === $productId && $configuredProductId !== '') {
                return (string) $plan;
            }
        }

        return null;
    }

    /**
     * Write a revenue outbox row with an IAP source_type.
     *
     * @param array<string, mixed> $payload
     */
    public function enqueueRevenueOutbox(
        string $store,
        string $notificationId,
        string $eventKind,
        array $payload,
    ): RevenueOutboxEvent {
        $sourceType = $store === 'apple'
            ? RevenueOutboxEvent::SOURCE_APPLE_IAP
            : RevenueOutboxEvent::SOURCE_GOOGLE_PLAY;

        return $this->subscriptionService->enqueueRevenueOutbox(
            sourceType: $sourceType,
            eventId: $notificationId,
            eventKind: $eventKind,
            payload: $payload,
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Apple persistence
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $input
     *
     * @return array{code?: string, context?: array<string, mixed>, success?: array<string, mixed>}
     */
    private function persistApple(
        AppleVerifiedTransaction $verified,
        User $user,
        string $plan,
        string $productId,
        array $input,
        ?string $remoteIp,
        ?string $userAgent,
    ): array {
        // 1. Look up existing row by originalTransactionId.
        /** @var IapSubscription|null $existing */
        $existing = IapSubscription::query()
            ->where('original_transaction_id', $verified->originalTransactionId)
            ->lockForUpdate()
            ->first();

        $isResubscribe = false;

        if ($existing !== null) {
            // Same Apple sub belonging to a different Zelta user → conflict.
            if ((int) $existing->user_id !== (int) $user->id) {
                return [
                    'code'    => 'ERR_SUB_002',
                    'context' => [
                        'conflict' => [
                            'kind'            => 'different_zelta_user',
                            'attemptedSource' => 'apple_iap',
                        ],
                    ],
                ];
            }

            // Fully expired → stale receipt.
            $terminalStatuses = [
                IapSubscription::STATUS_EXPIRED,
                IapSubscription::STATUS_REFUNDED,
                IapSubscription::STATUS_CANCELLED,
            ];
            $isTerminal = in_array($existing->status, $terminalStatuses, true);
            $expiryPast = $verified->expiresDate?->isPast() ?? true;
            if ($isTerminal && $expiryPast) {
                return ['code' => 'ERR_SUB_009'];
            }

            // Within-grace reactivation (cancel_at_period_end = true, not yet expired).
            if ($existing->cancel_at_period_end && $verified->expiresDate?->isFuture() === true) {
                $existing->cancel_at_period_end = false;
                $existing->current_period_ends_at = $verified->expiresDate;
                $existing->status = IapSubscription::STATUS_ACTIVE;
                $existing->save();
                $isResubscribe = true;
            } else {
                // Already-active no-op (same-store-duplicate): idempotent 200.
                return [
                    'success' => array_merge($this->projection->for($user->refresh()), [
                        'reactivated' => false,
                    ]),
                ];
            }

            $subscription = $existing;
        } else {
            $subscription = new IapSubscription([
                'id'      => (string) Str::uuid(),
                'user_id' => $user->id,
                'store'   => IapSubscription::STORE_APPLE,
                'tier'    => 'pro',
                'status'  => $verified->isTrialPeriod
                    ? IapSubscription::STATUS_TRIALING
                    : IapSubscription::STATUS_ACTIVE,
                'original_transaction_id'  => $verified->originalTransactionId,
                'apple_app_account_token'  => $verified->appAccountToken,
                'trial_started_at'         => $verified->isTrialPeriod ? $verified->purchaseDate : null,
                'trial_ends_at'            => $verified->isTrialPeriod ? $verified->expiresDate : null,
                'current_period_starts_at' => $verified->purchaseDate,
                'current_period_ends_at'   => $verified->expiresDate,
                'cancel_at_period_end'     => false,
                'last_notification_type'   => 'verify',
            ]);
            $subscription->save();
        }

        $this->appendEvent(
            aggregate: $subscription,
            eventClass: $isResubscribe ? 'AppleSubscriptionReactivated' : 'AppleSubscriptionVerified',
            payload: [
                'productId'             => $verified->productId,
                'originalTransactionId' => $verified->originalTransactionId,
                'transactionId'         => $verified->transactionId,
                'isTrialPeriod'         => $verified->isTrialPeriod,
                'isSandbox'             => $verified->isSandbox,
            ],
        );

        $this->writeReceipt(
            subscription: $subscription,
            store: IapSubscription::STORE_APPLE,
            verified: $verified,
            user: $user,
        );

        $this->writeConsentLogIfPresent($user, $subscription, $input, $remoteIp, $userAgent);

        $this->enqueueRevenueOutbox(
            store: IapSubscription::STORE_APPLE,
            notificationId: 'verify:' . $verified->transactionId,
            eventKind: $isResubscribe ? 'iap_subscription_reactivated' : 'iap_subscription_initial',
            payload: [
                'userId'       => $user->id,
                'aggregateId'  => $subscription->id,
                'amount'       => $verified->amountSmallestUnit,
                'decimals'     => $verified->amountDecimals,
                'denomination' => $verified->amountCurrency,
                'plan'         => $plan,
                'productId'    => $verified->productId,
                'emittedAt'    => now()->toIso8601String(),
                'rawType'      => 'apple_iap_verify',
            ],
        );

        return [
            'success' => array_merge($this->projection->for($user->refresh()), [
                'reactivated' => $isResubscribe,
            ]),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Google persistence
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $input
     *
     * @return array{code?: string, context?: array<string, mixed>, success?: array<string, mixed>}
     */
    private function persistGoogle(
        GoogleVerifiedSubscription $verified,
        User $user,
        string $plan,
        string $productId,
        array $input,
        ?string $remoteIp,
        ?string $userAgent,
    ): array {
        $purchaseTokenHash = hash_hmac(
            'sha256',
            $verified->purchaseToken,
            (string) config('subscription.iap.receipt_pepper', ''),
        );

        /** @var IapSubscription|null $existing */
        $existing = IapSubscription::query()
            ->where('play_subscription_resource_id', $verified->subscriptionResourceId)
            ->lockForUpdate()
            ->first();

        $isResubscribe = false;

        if ($existing !== null) {
            if ((int) $existing->user_id !== (int) $user->id) {
                return [
                    'code'    => 'ERR_SUB_002',
                    'context' => [
                        'conflict' => [
                            'kind'            => 'different_zelta_user',
                            'attemptedSource' => 'google_play',
                        ],
                    ],
                ];
            }

            // Stale: Google says the subscription is in a terminal state and
            // we already know about it.
            $googleTerminal = in_array($verified->state, [
                'SUBSCRIPTION_STATE_EXPIRED',
                'SUBSCRIPTION_STATE_CANCELED',
            ], true);
            if ($googleTerminal && ($verified->expiryTime?->isPast() ?? true)) {
                return ['code' => 'ERR_SUB_009'];
            }

            if ($existing->cancel_at_period_end && $verified->expiryTime?->isFuture() === true) {
                $existing->cancel_at_period_end = false;
                $existing->current_period_ends_at = $verified->expiryTime;
                $existing->status = IapSubscription::STATUS_ACTIVE;
                $existing->google_purchase_token_hash = $purchaseTokenHash;
                $existing->save();
                $isResubscribe = true;
            } else {
                return [
                    'success' => array_merge($this->projection->for($user->refresh()), [
                        'reactivated' => false,
                    ]),
                ];
            }

            $subscription = $existing;
        } else {
            $subscription = new IapSubscription([
                'id'      => (string) Str::uuid(),
                'user_id' => $user->id,
                'store'   => IapSubscription::STORE_GOOGLE,
                'tier'    => 'pro',
                'status'  => $verified->isTrialPeriod
                    ? IapSubscription::STATUS_TRIALING
                    : IapSubscription::STATUS_ACTIVE,
                'play_subscription_resource_id' => $verified->subscriptionResourceId,
                'google_purchase_token_hash'    => $purchaseTokenHash,
                'google_obfuscated_account_id'  => $verified->obfuscatedAccountId,
                'trial_started_at'              => $verified->isTrialPeriod ? $verified->startTime : null,
                'trial_ends_at'                 => $verified->isTrialPeriod ? $verified->expiryTime : null,
                'current_period_starts_at'      => $verified->startTime,
                'current_period_ends_at'        => $verified->expiryTime,
                'cancel_at_period_end'          => false,
                'last_notification_type'        => 'verify',
            ]);
            $subscription->save();
        }

        $this->appendEvent(
            aggregate: $subscription,
            eventClass: $isResubscribe ? 'GoogleSubscriptionReactivated' : 'GoogleSubscriptionVerified',
            payload: [
                'productId'              => $verified->productId,
                'subscriptionResourceId' => $verified->subscriptionResourceId,
                'state'                  => $verified->state,
                'isTrialPeriod'          => $verified->isTrialPeriod,
            ],
        );

        $this->writeReceipt(
            subscription: $subscription,
            store: IapSubscription::STORE_GOOGLE,
            verified: $verified,
            user: $user,
        );

        $this->writeConsentLogIfPresent($user, $subscription, $input, $remoteIp, $userAgent);

        $this->enqueueRevenueOutbox(
            store: IapSubscription::STORE_GOOGLE,
            notificationId: 'verify:' . hash('sha256', $verified->purchaseToken),
            eventKind: $isResubscribe ? 'iap_subscription_reactivated' : 'iap_subscription_initial',
            payload: [
                'userId'       => $user->id,
                'aggregateId'  => $subscription->id,
                'amount'       => $verified->amountSmallestUnit,
                'decimals'     => $verified->amountDecimals,
                'denomination' => $verified->amountCurrency,
                'plan'         => $plan,
                'productId'    => $verified->productId,
                'emittedAt'    => now()->toIso8601String(),
                'rawType'      => 'google_play_verify',
            ],
        );

        unset($productId);

        return [
            'success' => array_merge($this->projection->for($user->refresh()), [
                'reactivated' => $isResubscribe,
            ]),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Shared helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Apple: `appAccountToken` (UUID) must match `users.uuid`.
     * Google: `obfuscatedExternalAccountId` (SHA-256 hex of user.uuid) must match.
     *
     * If neither token is present we accept the binding (the store is the
     * authoritative authenticated source — mobile populates the token but it
     * is best-effort; the Sanctum bearer still scopes the request).
     */
    private function matchesAuthenticatedUser(
        AppleVerifiedTransaction|GoogleVerifiedSubscription $verified,
        User $user,
    ): bool {
        if ($verified instanceof AppleVerifiedTransaction) {
            $token = $verified->appAccountToken;
            if ($token === null || $token === '') {
                return true;
            }

            // Apple's appAccountToken is a UUID; mobile populates with user->uuid.
            return strcasecmp($token, (string) $user->uuid) === 0;
        }

        $token = $verified->obfuscatedAccountId;
        if ($token === null || $token === '') {
            return true;
        }

        $expected = hash('sha256', (string) $user->uuid);

        return hash_equals($expected, $token);
    }

    /**
     * Write a receipt row. The raw blob is stored for audit; pseudonymisation
     * nulls it later.
     */
    private function writeReceipt(
        IapSubscription $subscription,
        string $store,
        AppleVerifiedTransaction|GoogleVerifiedSubscription $verified,
        User $user,
    ): void {
        $data = [
            'user_id'             => $user->id,
            'iap_subscription_id' => $subscription->id,
            'store'               => $store,
            'product_id'          => $verified->productId,
            'tier'                => 'pro',
            'environment'         => $store === IapSubscription::STORE_APPLE
                && $verified instanceof AppleVerifiedTransaction
                && $verified->isSandbox
                    ? IapReceipt::ENV_SANDBOX
                    : IapReceipt::ENV_PRODUCTION,
            'amount_smallest_unit' => $verified->amountSmallestUnit,
            'amount_decimals'      => $verified->amountDecimals,
            'amount_currency'      => $verified->amountCurrency,
        ];

        if ($verified instanceof AppleVerifiedTransaction) {
            $data['original_transaction_id'] = $verified->originalTransactionId;
            $data['transaction_id'] = $verified->transactionId;
            $data['apple_app_account_token'] = $verified->appAccountToken;
            $data['receipt_blob'] = $verified->rawJws;
            $data['period_starts_at'] = $verified->purchaseDate;
            $data['period_ends_at'] = $verified->expiresDate;
        } else {
            /** @var GoogleVerifiedSubscription $verified */
            $data['google_obfuscated_account_id'] = $verified->obfuscatedAccountId;
            $data['receipt_blob'] = $verified->purchaseToken;
            $data['period_starts_at'] = $verified->startTime;
            $data['period_ends_at'] = $verified->expiryTime;
        }

        try {
            IapReceipt::query()->create($data);
        } catch (QueryException $e) {
            // Duplicate receipt row for the same originalTransactionId is fine
            // (mobile retry storm). Log and move on — the subscription row is
            // already idempotent on its own key.
            if ($this->isDuplicateKeyError($e)) {
                Log::info('iap.receipt.duplicate', [
                    'store'   => $store,
                    'sub_id'  => $subscription->id,
                    'user_id' => $user->id,
                ]);

                return;
            }

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function appendEvent(IapSubscription $aggregate, string $eventClass, array $payload): void
    {
        // Two concurrent webhook deliveries for the same aggregate (e.g.
        // SUBSCRIBED + DID_RENEW arriving simultaneously) used to both read the
        // same max(aggregate_version) and race to insert maxVersion+1 — one
        // would commit, the other would hit the unique constraint on
        // (aggregate_uuid, aggregate_version) and be lost via the outer
        // Throwable catch in the webhook controllers. Hold the aggregate's
        // event rows under lockForUpdate so the max read + insert is atomic
        // for the duration of the enclosing DB::transaction().
        /** @var int $maxVersion */
        $maxVersion = (int) IapSubscriptionEvent::query()
            ->where('aggregate_uuid', $aggregate->id)
            ->lockForUpdate()
            ->max('aggregate_version');

        IapSubscriptionEvent::query()->create([
            'id'                => (string) Str::uuid(),
            'aggregate_uuid'    => $aggregate->id,
            'aggregate_version' => $maxVersion + 1,
            'event_class'       => $eventClass,
            'event_payload'     => $payload,
            'metadata'          => [
                'recordedAt' => now()->toIso8601String(),
                'store'      => $aggregate->store,
            ],
            'created_at' => Carbon::now()->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * @param array<string, mixed> $input  unverified request body — withdrawalConsent
     *                                     is the only field we read.
     */
    private function writeConsentLogIfPresent(
        User $user,
        IapSubscription $subscription,
        array $input,
        ?string $remoteIp,
        ?string $userAgent,
    ): void {
        $consent = $input['withdrawalConsent'] ?? null;
        if (! is_array($consent)) {
            Log::debug('iap.verify.withdrawal_consent_absent', [
                'user_id'         => $user->id,
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        if (! $this->consent->isWellFormed($consent)) {
            Log::warning('iap.verify.withdrawal_consent_malformed', [
                'user_id' => $user->id,
            ]);

            return;
        }

        $ipHash = hash_hmac('sha256', $remoteIp ?? '0.0.0.0', (string) config('app.key'));

        SubscriptionConsentLog::query()->create([
            'user_id'         => $user->id,
            'subscription_id' => null, // FK targets cashier subscriptions; IAP path uses nullable.
            'consent_text'    => (string) $consent['consentText'],
            'consent_version' => (int) $consent['version'],
            'shown_at'        => $consent['shownAt'],
            'accepted_at'     => $consent['acceptedAt'],
            'ip_hash'         => $ipHash,
            'user_agent'      => $userAgent,
        ]);
    }

    private function isDuplicateKeyError(QueryException|Throwable $e): bool
    {
        $sqlState = (string) ($e instanceof QueryException ? $e->getCode() : '');

        return $sqlState === '23000' || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
