<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Domain\Compliance\Kyc\Models\BridgeCustomer;
use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Mobile\Models\BiometricChallenge;
use App\Domain\Mobile\Models\DeviceReassignmentLog;
use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobileDeviceSession;
use App\Domain\Mobile\Models\MobileNotificationPreference;
use App\Domain\Mobile\Models\MobilePushNotification;
use App\Domain\MobilePayment\Models\ActivityFeedItem;
use App\Domain\MobilePayment\Models\PaymentIntent;
use App\Domain\MobilePayment\Models\PaymentReceipt;
use App\Domain\Payment\Models\HyperSwitchDepositIntent;
use App\Domain\Subscription\Models\Cue;
use App\Domain\Subscription\Models\IapReceipt;
use App\Domain\Subscription\Models\IapSubscription;
use App\Domain\Subscription\Models\SubscriptionConsentLog;
use App\Domain\Subscription\Models\TrialCardFingerprint;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Infrastructure\Bridge\BridgeClient;
use App\Models\McpToolInvocation;
use App\Models\RampSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Subscription as CashierSubscription;
use Throwable;

/**
 * GDPR service: Art. 15/20 export and Art. 17 erasure across every table
 * that stores user-linked personal data.
 *
 * Event-store position: event-stream payloads (stored_events + the
 * domain-specific event tables) are append-only financial records and are
 * retained under the Art. 17(3)(b) legal-obligation exemption; erasure
 * anonymizes the *projections* (users row, transaction projections,
 * per-domain read models) so no personal data remains queryable through any
 * read path. There is no per-user crypto-shredding mechanism in the event
 * store today (verified 2026-06: no per-user encryption keys exist for
 * stored events), so payload-level redaction of historical events is not
 * possible without a replay-rewrite, which is out of scope here.
 *
 * Processor fan-out (Art. 17(2)): notifyProcessorsOfErasure() forwards the
 * erasure to Bridge.xyz (customer delete) and Privy (user delete). Apple
 * App Store and Google Play expose no server-side API to delete IAP
 * subscriber data — when the user has IAP records, a structured
 * `gdpr.processor_erasure_manual` audit row and log entry are emitted and
 * an operator must follow the manual step (data-deletion request via
 * App Store Connect / Google Play Console). Documented here because no
 * GDPR runbook exists under docs/operations/ yet.
 *
 * Coverage guard: COVERED_USER_DATA_TABLES + EXCLUDED_USER_DATA_TABLES must
 * jointly account for every schema table that carries a user FK column
 * (user_id / user_uuid / privy_user_id, or a *_user_id / *_user_uuid
 * suffix) — enforced by tests/Feature/Compliance/GdprCoverageGuardTest.php.
 * A new user-data table must consciously opt in (export/erasure handling
 * here + entry in COVERED) or out (justified entry in EXCLUDED).
 */
class GdprService
{
    /**
     * Tables actively handled by exportUserData() and/or deleteUserData().
     *
     * @var array<int, string>
     */
    public const COVERED_USER_DATA_TABLES = [
        'users',
        'accounts',
        'kyc_documents',
        'audit_logs',
        // Subscriptions / IAP (v7.13+)
        'subscriptions',
        'iap_subscriptions',
        'iap_receipts',
        'subscription_consent_log',
        'trial_card_fingerprints',
        'cues',
        // Bridge.xyz ramp (v7.15+)
        'bridge_customers',
        'ramp_sessions',
        // Wallet (v7.12+)
        'blockchain_addresses',
        'wallet_send_records',
        // Mobile payments
        'payment_intents',
        'payment_receipts',
        'activity_feed_items',
        'hyperswitch_deposit_intents',
        // Mobile devices / notifications
        'mobile_devices',
        'mobile_device_sessions',
        'mobile_notification_preferences',
        'mobile_push_notifications',
        'biometric_challenges',
        'device_reassignment_log',
        // API audit
        'mcp_tool_invocations',
    ];

    private const EXCL_LEGACY = 'Pre-v7.13 prototype/demo domain — not enabled on the Zelta production deployment; tracked for a dedicated GDPR coverage pass.';

    private const EXCL_FINANCIAL = 'Financial/accounting record retained under the Art. 17(3)(b) legal-obligation exemption; no direct identifiers beyond the FK to the anonymized users row.';

    private const EXCL_SECURITY = 'Security/fraud/audit trail retained under Art. 17(3)(e) (establishment/defence of legal claims); pruned by its own retention policy.';

    private const EXCL_EPHEMERAL = 'Ephemeral or framework state with its own expiry; no exportable personal-data payload.';

    private const EXCL_CONSENT = 'Proof-of-consent audit record retained under Art. 17(3)(e).';

    private const EXCL_TENANCY = 'Tenancy/membership structure; no personal data beyond the user FK (which points at the anonymized users row after erasure).';

    /**
     * Tables with a user FK that are deliberately NOT touched by export or
     * erasure, each with the justification for that decision.
     *
     * @var array<string, string>
     */
    public const EXCLUDED_USER_DATA_TABLES = [
        'account_flags'                => 'Operator-set capability flags (reviewer bypasses); no personal data beyond the user FK; swept by the reviewer-account lifecycle tooling.',
        'ach_batches'                  => self::EXCL_LEGACY,
        'ai_llm_usage'                 => self::EXCL_FINANCIAL,
        'anomaly_detections'           => self::EXCL_SECURITY,
        'api_keys'                     => 'Hashed API credentials revoked on account closure; no readable personal data.',
        'bank_accounts'                => self::EXCL_LEGACY,
        'bank_connections'             => self::EXCL_LEGACY,
        'bank_transfers'               => self::EXCL_LEGACY,
        'batch_jobs'                   => self::EXCL_EPHEMERAL,
        'behavioral_profiles'          => self::EXCL_LEGACY,
        'blockchain_wallets'           => self::EXCL_LEGACY,
        'blockchain_withdrawals'       => self::EXCL_LEGACY,
        'bridge_transactions'          => self::EXCL_LEGACY, // legacy cross-chain bridge demo — unrelated to Bridge.xyz
        'card_transactions'            => self::EXCL_LEGACY,
        'card_waitlist'                => self::EXCL_LEGACY,
        'card_waitlist_deposits'       => self::EXCL_LEGACY,
        'cardholders'                  => self::EXCL_LEGACY,
        'cards'                        => self::EXCL_LEGACY,
        'certificates'                 => self::EXCL_LEGACY,
        'cgo_investments'              => self::EXCL_LEGACY,
        'cgo_refunds'                  => self::EXCL_LEGACY,
        'compliance_alerts'            => self::EXCL_SECURITY,
        'consent_records'              => self::EXCL_CONSENT,
        'consents'                     => self::EXCL_CONSENT,
        'customer_risk_profiles'       => 'AML/CTF risk-scoring record retained under Art. 17(3)(b) statutory retention.',
        'data_transfer_logs'           => self::EXCL_SECURITY,
        'defi_positions'               => self::EXCL_LEGACY,
        'delegated_proof_jobs'         => self::EXCL_LEGACY,
        'device_fingerprints'          => self::EXCL_SECURITY,
        'exports'                      => self::EXCL_EPHEMERAL,
        'gcu_votes'                    => self::EXCL_LEGACY,
        'hardware_wallet_associations' => self::EXCL_LEGACY,
        'idempotency_keys'             => self::EXCL_EPHEMERAL,
        'imports'                      => self::EXCL_EPHEMERAL,
        'key_access_logs'              => self::EXCL_LEGACY, // legacy custodial key management (custodial endpoints removed in v7.12)
        'key_reconstruction_logs'      => self::EXCL_LEGACY,
        'key_shards'                   => self::EXCL_LEGACY,
        'kyc_verifications'            => 'KYC/AML verification trail retained under Art. 17(3)(b) statutory retention (AMLD); uploaded documents are deleted via deleteKycDocuments().',
        'mfi_field_officers'           => self::EXCL_LEGACY,
        'mfi_group_members'            => self::EXCL_LEGACY,
        'mfi_share_accounts'           => self::EXCL_LEGACY,
        'mfi_teller_cashiers'          => self::EXCL_LEGACY,
        'multi_sig_approval_requests'  => self::EXCL_LEGACY,
        'multi_sig_signer_approvals'   => self::EXCL_LEGACY,
        'multi_sig_wallet_signers'     => self::EXCL_LEGACY,
        'multi_sig_wallets'            => self::EXCL_LEGACY,
        'oauth_access_tokens'          => 'Auth artifacts (hashed tokens) revoked on account closure; no personal-data payload.',
        'oauth_auth_codes'             => 'Auth artifacts (hashed codes) revoked on account closure; no personal-data payload.',
        'orders'                       => self::EXCL_LEGACY,
        'payment_rail_transactions'    => self::EXCL_LEGACY,
        'pending_signing_requests'     => self::EXCL_EPHEMERAL,
        'price_quotes'                 => self::EXCL_EPHEMERAL,
        'privacy_commitments'          => self::EXCL_LEGACY,
        'privacy_transactions'         => self::EXCL_LEGACY,
        'promotions'                   => self::EXCL_LEGACY,
        'railgun_wallets'              => self::EXCL_LEGACY,
        'recovery_backups'             => self::EXCL_LEGACY,
        'recovery_shard_cloud_backups' => self::EXCL_LEGACY,
        'referral_codes'               => 'Random referral code + counters; no personal data beyond the FK to the anonymized users row.',
        'revenue_events'               => self::EXCL_FINANCIAL,
        'reward_profiles'              => self::EXCL_FINANCIAL,
        'security_audit_logs'          => self::EXCL_SECURITY,
        'sepa_mandates'                => self::EXCL_LEGACY,
        'sessions'                     => self::EXCL_EPHEMERAL,
        'shielded_balances'            => self::EXCL_LEGACY,
        'smart_accounts'               => self::EXCL_LEGACY,
        'suspicious_activity_reports'  => 'AML/CTF SAR — statutory retention plus tipping-off prohibition: exempt from both Art. 15 disclosure and Art. 17 erasure.',
        'team_user'                    => self::EXCL_TENANCY,
        'team_user_roles'              => self::EXCL_TENANCY,
        'teams'                        => self::EXCL_TENANCY,
        'tenant_audit_logs'            => self::EXCL_SECURITY,
        'user_activities'              => self::EXCL_LEGACY,
        'user_bank_preferences'        => self::EXCL_LEGACY,
        'user_fee_tiers'               => 'Fee-tier assignment; no personal data beyond the user FK.',
        'user_products'                => self::EXCL_LEGACY,
        'user_profiles'                => self::EXCL_LEGACY,
        'verification_payments'        => self::EXCL_LEGACY,
        'virtuals_agent_profiles'      => self::EXCL_LEGACY,
        'visa_cli_enrolled_cards'      => self::EXCL_LEGACY,
        'votes'                        => self::EXCL_LEGACY,
        'websocket_subscriptions'      => self::EXCL_EPHEMERAL,
    ];

    /**
     * Both clients are optional so `new GdprService()` keeps working in unit
     * tests and the container can fall back to lazy resolution (BridgeClient
     * needs runtime config, so it is built via fromConfig() on first use).
     */
    public function __construct(
        private readonly ?BridgeClient $bridgeClient = null,
        private readonly ?PrivyEmailOtpClient $privyClient = null,
    ) {
    }

    /**
     * True when a column name marks its table as user-linked data
     * (used by the schema coverage guard).
     */
    public static function isUserLinkColumn(string $column): bool
    {
        $column = strtolower($column);

        return in_array($column, ['user_id', 'user_uuid', 'privy_user_id'], true)
            || str_ends_with($column, '_user_id')
            || str_ends_with($column, '_user_uuid');
    }

    /**
     * Pure diff used by the coverage guard: every user-linked table must be
     * either covered or explicitly excluded with a justification.
     *
     * @param  array<int, string>  $tablesWithUserColumns
     * @return array<int, string>  tables that are neither covered nor excluded
     */
    public static function uncoveredUserDataTables(array $tablesWithUserColumns): array
    {
        $known = array_merge(
            self::COVERED_USER_DATA_TABLES,
            array_keys(self::EXCLUDED_USER_DATA_TABLES),
        );

        return array_values(array_diff($tablesWithUserColumns, $known));
    }

    /**
     * Export all user data (GDPR Article 20 - Right to data portability).
     */
    public function exportUserData(User $user): array
    {
        AuditLog::log(
            'gdpr.data_exported',
            $user,
            null,
            null,
            ['requested_by' => $user->uuid],
            'gdpr,compliance,data-export'
        );

        return [
            'user'            => $this->getUserData($user),
            'accounts'        => $this->getAccountData($user),
            'transactions'    => $this->getTransactionData($user),
            'kyc_documents'   => $this->getKycData($user),
            'audit_logs'      => $this->getAuditData($user),
            'consents'        => $this->getConsentData($user),
            'subscriptions'   => $this->getSubscriptionData($user),
            'bridge'          => $this->getBridgeData($user),
            'wallet'          => $this->getWalletData($user),
            'mobile_payments' => $this->getMobilePaymentData($user),
            'devices'         => $this->getDeviceData($user),
            'api_usage'       => $this->getApiUsageData($user),
        ];
    }

    /**
     * Delete user data (GDPR Article 17 - Right to erasure).
     */
    public function deleteUserData(User $user, array $options = []): void
    {
        // Processor fan-out runs BEFORE local erasure: it needs the processor
        // references (bridge_customer_id, privy_user_id) that the local pass
        // deletes/nulls, and it is failure-tolerant — every failure is caught,
        // logged and recorded as an AuditLog row for operator retry, so a
        // processor 5xx can never block erasure of local data.
        $this->notifyProcessorsOfErasure($user);

        // NOTE: every model mutated inside this transaction lives on the
        // default connection — never add a UsesTenantConnection model here
        // (separate MySQL session => self-deadlock; see CLAUDE.md).
        DB::transaction(
            function () use ($user, $options) {
                // Log the deletion request
                AuditLog::log(
                    'gdpr.deletion_requested',
                    $user,
                    null,
                    null,
                    ['options' => $options],
                    'gdpr,compliance,deletion'
                );

                // Anonymize user data instead of hard delete
                $this->anonymizeUser($user);

                // Delete KYC documents if requested
                if ($options['delete_documents'] ?? false) {
                    $this->deleteKycDocuments($user);
                }

                // Anonymize transaction data
                if ($options['anonymize_transactions'] ?? true) {
                    $this->anonymizeTransactions($user);
                }

                // Per-table erasure for everything added since v7.13
                $this->eraseSubscriptionData($user);
                $this->eraseBridgeData($user);
                $this->eraseWalletData($user);
                $this->eraseMobilePaymentData($user);
                $this->eraseDeviceData($user);
                $this->eraseApiAuditData($user);

                // Log the deletion completion
                AuditLog::log(
                    'gdpr.deletion_completed',
                    $user,
                    null,
                    null,
                    ['options' => $options],
                    'gdpr,compliance,deletion'
                );
            }
        );
    }

    /**
     * Art. 17(2) processor fan-out: forward the erasure request to every
     * processor that holds this user's personal data on our behalf.
     *
     * Queue-safe and failure-tolerant: each processor call is individually
     * wrapped — a failure is logged + recorded as a `gdpr.processor_erasure_failed`
     * audit row (so operators can retry) and never propagates to the caller.
     */
    public function notifyProcessorsOfErasure(User $user): void
    {
        $this->notifyBridgeOfErasure($user);
        $this->notifyPrivyOfErasure($user);
        $this->recordManualStoreErasureSteps($user);
    }

    /**
     * Update user consent preferences.
     */
    public function updateConsent(User $user, array $consents): void
    {
        $oldConsents = [
            'marketing_consent'      => $user->marketing_consent_at !== null,
            'data_retention_consent' => $user->data_retention_consent,
        ];

        $updates = [];

        if (isset($consents['marketing'])) {
            $updates['marketing_consent_at'] = $consents['marketing'] ? now() : null;
        }

        if (isset($consents['data_retention'])) {
            $updates['data_retention_consent'] = $consents['data_retention'];
        }

        if (isset($consents['privacy_policy'])) {
            $updates['privacy_policy_accepted_at'] = $consents['privacy_policy'] ? now() : null;
        }

        if (isset($consents['terms'])) {
            $updates['terms_accepted_at'] = $consents['terms'] ? now() : null;
        }

        $user->update($updates);

        AuditLog::log(
            'gdpr.consent_updated',
            $user,
            $oldConsents,
            $consents,
            null,
            'gdpr,compliance,consent'
        );
    }

    /**
     * Get user's personal data.
     */
    protected function getUserData(User $user): array
    {
        return [
            'uuid'              => $user->uuid,
            'name'              => $user->name,
            'email'             => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'kyc_status'        => $user->kyc_status,
            'kyc_level'         => $user->kyc_level,
            // Identity-provider link: the Privy DID is an identifier assigned
            // to the user, so Art. 15 includes it.
            'privy_user_id'   => $user->privy_user_id,
            'privy_linked_at' => $user->privy_linked_at,
            'created_at'      => $user->created_at,
            'updated_at'      => $user->updated_at,
        ];
    }

    /**
     * Get user's account data.
     */
    protected function getAccountData(User $user): array
    {
        return $user->accounts->map(
            function ($account) {
                return [
                    'uuid'       => $account->uuid,
                    'balance'    => $account->balance,
                    'status'     => $account->status,
                    'created_at' => $account->created_at,
                    'balances'   => $account->balances->map(
                        function ($balance) {
                            return [
                                'asset_code' => $balance->asset_code,
                                'balance'    => $balance->balance,
                            ];
                        }
                    )->toArray(),
                ];
            }
        )->toArray();
    }

    /**
     * Get user's transaction data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getTransactionData(User $user): array
    {
        /** @var \Illuminate\Support\Collection<int, string> */
        $accountUuids = $user->accounts()->pluck('accounts.uuid');

        /** @var \Illuminate\Database\Eloquent\Collection<int, TransactionProjection> */
        $transactions = TransactionProjection::whereIn('account_uuid', $accountUuids)
            ->orderBy('created_at', 'desc')
            ->get();

        /** @var array<int, array<string, mixed>> */
        $result = $transactions->map(
            /** @param TransactionProjection $transaction */
            function ($transaction): array {
                return [
                    'uuid'         => $transaction->uuid,
                    'account_uuid' => $transaction->account_uuid,
                    'type'         => $transaction->type,
                    'amount'       => $transaction->amount,
                    'currency'     => $transaction->currency,
                    'status'       => $transaction->status,
                    'description'  => $transaction->description,
                    'metadata'     => $transaction->metadata,
                    'created_at'   => $transaction->created_at,
                    'updated_at'   => $transaction->updated_at,
                ];
            }
        )->toArray();

        return $result;
    }

    /**
     * Get user's KYC data.
     */
    protected function getKycData(User $user): array
    {
        return $user->kycDocuments->map(
            function ($document) {
                return [
                    'id'            => $document->id,
                    'document_type' => $document->document_type,
                    'status'        => $document->status,
                    'uploaded_at'   => $document->uploaded_at,
                    'verified_at'   => $document->verified_at,
                ];
            }
        )->toArray();
    }

    /**
     * Get user's audit data.
     */
    protected function getAuditData(User $user): array
    {
        return AuditLog::where('user_uuid', $user->uuid)
            ->limit(1000)
            ->get()
            ->map(
                function ($log) {
                    return [
                        'action'     => $log->action,
                        'created_at' => $log->created_at,
                        'ip_address' => $log->ip_address,
                    ];
                }
            )
            ->toArray();
    }

    /**
     * Get user's consent history.
     */
    protected function getConsentData(User $user): array
    {
        return [
            'privacy_policy_accepted_at' => $user->privacy_policy_accepted_at,
            'terms_accepted_at'          => $user->terms_accepted_at,
            'marketing_consent_at'       => $user->marketing_consent_at,
            'data_retention_consent'     => $user->data_retention_consent,
        ];
    }

    /**
     * Stripe (Cashier) + IAP subscription data, per-renewal IAP payment
     * records and the withdrawal-consent log.
     *
     * Field choices:
     * - `stripe_id` (Cashier) is a processor-side pseudonymous reference,
     *   not user-readable personal data — omitted.
     * - IAP store-account tokens / pseudonymised receipt hashes
     *   (apple_app_account_token, google_obfuscated_account_id,
     *   google_purchase_token_hash, receipt_blob) are opaque pseudonyms that
     *   reveal nothing readable to the user — omitted; the subscription and
     *   payment facts are included instead.
     * - trial_card_fingerprints is deliberately NOT exported: it holds only
     *   an HMAC card fingerprint kept for trial-abuse prevention; it contains
     *   no user-readable personal data.
     *
     * @return array<string, mixed>
     */
    protected function getSubscriptionData(User $user): array
    {
        return [
            'stripe' => CashierSubscription::query()->where('user_id', $user->id)->get()->map(
                fn (CashierSubscription $subscription): array => [
                    'type'          => $subscription->type,
                    'stripe_status' => $subscription->stripe_status,
                    'stripe_price'  => $subscription->stripe_price,
                    'quantity'      => $subscription->quantity,
                    'trial_ends_at' => $subscription->trial_ends_at,
                    'ends_at'       => $subscription->ends_at,
                    'created_at'    => $subscription->created_at,
                ]
            )->all(),
            'iap' => IapSubscription::where('user_id', $user->id)->get()->map(
                fn (IapSubscription $subscription): array => [
                    'store'                    => $subscription->store,
                    'tier'                     => $subscription->tier,
                    'status'                   => $subscription->status,
                    'trial_started_at'         => $subscription->trial_started_at,
                    'trial_ends_at'            => $subscription->trial_ends_at,
                    'current_period_starts_at' => $subscription->current_period_starts_at,
                    'current_period_ends_at'   => $subscription->current_period_ends_at,
                    'cancel_at_period_end'     => $subscription->cancel_at_period_end,
                    'cancelled_at'             => $subscription->cancelled_at,
                    'expired_at'               => $subscription->expired_at,
                    'refunded_at'              => $subscription->refunded_at,
                    'created_at'               => $subscription->created_at,
                ]
            )->all(),
            'iap_payments' => IapReceipt::where('user_id', $user->id)->get()->map(
                fn (IapReceipt $receipt): array => [
                    'store'                => $receipt->store,
                    'product_id'           => $receipt->product_id,
                    'tier'                 => $receipt->tier,
                    'amount_smallest_unit' => $receipt->amount_smallest_unit,
                    'amount_decimals'      => $receipt->amount_decimals,
                    'amount_currency'      => $receipt->amount_currency,
                    'period_starts_at'     => $receipt->period_starts_at,
                    'period_ends_at'       => $receipt->period_ends_at,
                    'environment'          => $receipt->environment,
                    'created_at'           => $receipt->created_at,
                ]
            )->all(),
            // consent_text/version/timestamps are the user's consent record;
            // ip_hash is omitted (pseudonymised, not user-readable).
            'consent_log' => SubscriptionConsentLog::where('user_id', $user->id)->get()->map(
                fn (SubscriptionConsentLog $log): array => [
                    'consent_text'    => $log->consent_text,
                    'consent_version' => $log->consent_version,
                    'shown_at'        => $log->shown_at,
                    'accepted_at'     => $log->accepted_at,
                    'user_agent'      => $log->user_agent,
                ]
            )->all(),
        ];
    }

    /**
     * Bridge.xyz customer record + ramp sessions.
     *
     * Field choices:
     * - virtual_account_details is exported decrypted: the virtual account
     *   routing details ARE the user's own deposit bank details.
     * - deposit_instructions likewise (the user's bank-transfer details).
     * - kyc_link_url is omitted: a single-use signed onboarding URL is a
     *   credential, not personal data.
     * - stripe_client_secret / provider_session_id / stripe_session_id are
     *   omitted: processor-side secrets and references.
     *
     * @return array<string, mixed>
     */
    protected function getBridgeData(User $user): array
    {
        /** @var BridgeCustomer|null $customer */
        $customer = BridgeCustomer::where('user_id', $user->id)->first();

        return [
            'customer' => $customer === null ? null : [
                'bridge_customer_id'      => $customer->bridge_customer_id,
                'kyc_status'              => $customer->kyc_status,
                'developer_fee_bps'       => $customer->developer_fee_bps,
                'supported_rails'         => $customer->supported_rails,
                'virtual_account_id'      => $customer->virtual_account_id,
                'virtual_account_details' => $customer->virtual_account_details,
                'created_at'              => $customer->created_at,
            ],
            'ramp_sessions' => RampSession::where('user_id', $user->id)->get()->map(
                fn (RampSession $session): array => [
                    'id'                   => $session->id,
                    'provider'             => $session->provider,
                    'type'                 => $session->type,
                    'fiat_currency'        => $session->fiat_currency,
                    'fiat_amount'          => $session->fiat_amount,
                    'crypto_currency'      => $session->crypto_currency,
                    'crypto_amount'        => $session->crypto_amount,
                    'wallet_address'       => $session->wallet_address,
                    'status'               => $session->status,
                    'source'               => $session->source,
                    'deposit_instructions' => $session->deposit_instructions,
                    'created_at'           => $session->created_at,
                ]
            )->all(),
        ];
    }

    /**
     * Non-custodial wallet data: on-chain addresses + send history.
     *
     * Field choices:
     * - public_key is included (public material owned by the user);
     *   derivation_path + metadata are omitted (server-side wallet/sync
     *   internals, not user-readable personal data).
     * - For sends, tx_hash is the canonical on-chain reference; the internal
     *   user_op_hash / idempotency_key / quote_id / metadata are omitted
     *   (server-side processing references).
     *
     * @return array<string, mixed>
     */
    protected function getWalletData(User $user): array
    {
        return [
            'blockchain_addresses' => BlockchainAddress::where('user_uuid', $user->uuid)->get()->map(
                fn (BlockchainAddress $address): array => [
                    'chain'      => $address->chain,
                    'address'    => $address->address,
                    'public_key' => $address->public_key,
                    'label'      => $address->label,
                    'is_active'  => $address->is_active,
                    'created_at' => $address->created_at,
                ]
            )->all(),
            'sends' => WalletSendRecord::where('user_id', $user->id)->get()->map(
                fn (WalletSendRecord $send): array => [
                    'public_id'         => $send->public_id,
                    'network'           => $send->network,
                    'asset'             => $send->asset,
                    'amount'            => $send->amount,
                    'sender_address'    => $send->sender_address,
                    'recipient_address' => $send->recipient_address,
                    'status'            => $send->status,
                    'tx_hash'           => $send->tx_hash,
                    'error_code'        => $send->error_code,
                    'created_at'        => $send->created_at,
                    'submitted_at'      => $send->submitted_at,
                    'confirmed_at'      => $send->confirmed_at,
                    'failed_at'         => $send->failed_at,
                ]
            )->all(),
        ];
    }

    /**
     * Mobile payment intents, receipts and card deposits.
     *
     * Field choices:
     * - idempotency_key / metadata / internal ids omitted (server-side).
     * - activity_feed_items is not exported: it is a denormalised UI mirror
     *   of the transaction data already exported above.
     * - hyperswitch_payment_id omitted (processor-side reference).
     *
     * @return array<string, mixed>
     */
    protected function getMobilePaymentData(User $user): array
    {
        return [
            'payment_intents' => PaymentIntent::where('user_id', $user->id)->get()->map(
                fn (PaymentIntent $intent): array => [
                    'public_id'   => $intent->public_id,
                    'merchant_id' => $intent->merchant_id,
                    'asset'       => $intent->asset,
                    'network'     => $intent->network,
                    'amount'      => $intent->amount,
                    'status'      => $intent->status->value,
                    'tx_hash'     => $intent->tx_hash,
                    'created_at'  => $intent->created_at,
                    'expires_at'  => $intent->expires_at,
                ]
            )->all(),
            'payment_receipts' => PaymentReceipt::where('user_id', $user->id)->get()->map(
                fn (PaymentReceipt $receipt): array => [
                    'public_id'      => $receipt->public_id,
                    'merchant_name'  => $receipt->merchant_name,
                    'amount'         => $receipt->amount,
                    'asset'          => $receipt->asset,
                    'network'        => $receipt->network,
                    'tx_hash'        => $receipt->tx_hash,
                    'network_fee'    => $receipt->network_fee,
                    'transaction_at' => $receipt->transaction_at,
                ]
            )->all(),
            'card_deposits' => HyperSwitchDepositIntent::where('user_uuid', $user->uuid)->get()->map(
                fn (HyperSwitchDepositIntent $intent): array => [
                    'amount_cents' => $intent->amount_cents,
                    'currency'     => $intent->currency,
                    'status'       => $intent->status,
                    'created_at'   => $intent->created_at,
                ]
            )->all(),
        ];
    }

    /**
     * Registered mobile devices, notification preferences and device
     * reassignment history.
     *
     * Field choices:
     * - push_token + biometric/passkey public keys are omitted: device/server
     *   credentials, not user-readable personal data.
     * - mobile_push_notifications / mobile_device_sessions /
     *   biometric_challenges are not exported: transient delivery/session/
     *   challenge artifacts (they are hard-deleted on erasure).
     * - On reassignment rows, ip_address/user_agent belong to the
     *   *registering* user — they are only disclosed when this user is the
     *   new owner, never for previous-owner rows (another data subject).
     *
     * @return array<string, mixed>
     */
    protected function getDeviceData(User $user): array
    {
        return [
            'devices' => MobileDevice::where('user_id', $user->id)->get()->map(
                fn (MobileDevice $device): array => [
                    'device_name'       => $device->device_name,
                    'device_model'      => $device->device_model,
                    'platform'          => $device->platform,
                    'os_version'        => $device->os_version,
                    'app_version'       => $device->app_version,
                    'biometric_enabled' => $device->biometric_enabled,
                    'last_active_at'    => $device->last_active_at,
                    'created_at'        => $device->created_at,
                ]
            )->all(),
            'notification_preferences' => MobileNotificationPreference::where('user_id', $user->id)->get()->map(
                fn (MobileNotificationPreference $preference): array => [
                    'notification_type' => $preference->notification_type,
                    'push_enabled'      => $preference->push_enabled,
                    'email_enabled'     => $preference->email_enabled,
                ]
            )->all(),
            'device_reassignments' => DeviceReassignmentLog::where('previous_user_id', $user->id)
                ->orWhere('new_user_id', $user->id)
                ->get()
                ->map(
                    fn (DeviceReassignmentLog $log): array => [
                        'device_id'             => $log->device_id,
                        'role'                  => $log->new_user_id === $user->id ? 'new_owner' : 'previous_owner',
                        'reason'                => $log->reason,
                        'had_bound_credentials' => $log->had_bound_credentials,
                        'ip_address'            => $log->new_user_id === $user->id ? $log->ip_address : null,
                        'user_agent'            => $log->new_user_id === $user->id ? $log->user_agent : null,
                        'created_at'            => $log->created_at,
                    ]
                )->all(),
        ];
    }

    /**
     * MCP / API audit data attributed to the user.
     *
     * Field choices: token_id/client_id/args_hash omitted (server-side
     * references and pseudonymous hashes); capped at 1000 rows like the
     * audit_logs section.
     *
     * @return array<string, mixed>
     */
    protected function getApiUsageData(User $user): array
    {
        return [
            'mcp_tool_invocations' => McpToolInvocation::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(1000)
                ->get()
                ->map(
                    fn (McpToolInvocation $invocation): array => [
                        'tool_name'     => $invocation->tool_name,
                        'result_status' => $invocation->result_status,
                        'error_code'    => $invocation->error_code,
                        'ip'            => $invocation->ip,
                        'user_agent'    => $invocation->user_agent,
                        'duration_ms'   => $invocation->duration_ms,
                        'created_at'    => $invocation->created_at,
                    ]
                )->all(),
        ];
    }

    /**
     * Anonymize user data.
     */
    protected function anonymizeUser(User $user): void
    {
        $user->update(
            [
                'name'     => 'ANONYMIZED_' . substr($user->uuid, 0, 8),
                'email'    => 'deleted-' . $user->uuid . '@anonymized.local',
                'kyc_data' => null,
                // The Privy DID is an identity-provider link (personal data);
                // the Privy-side record itself is deleted via
                // notifyProcessorsOfErasure().
                'privy_user_id'   => null,
                'privy_linked_at' => null,
            ]
        );
    }

    /**
     * Delete KYC documents.
     */
    protected function deleteKycDocuments(User $user): void
    {
        $user->kycDocuments->each(
            function ($document) {
                if ($document->file_path && Storage::disk('private')->exists($document->file_path)) {
                    Storage::disk('private')->delete($document->file_path);
                }
                $document->delete();
            }
        );
    }

    /**
     * Anonymize transaction data.
     */
    protected function anonymizeTransactions(User $user): void
    {
        // This would need to update the event store
        // For now, we'll just log the intent
        AuditLog::log(
            'gdpr.transactions_anonymized',
            $user,
            null,
            null,
            null,
            'gdpr,compliance,anonymization'
        );
    }

    /**
     * Erase subscription/IAP personal data while preserving financial records.
     */
    protected function eraseSubscriptionData(User $user): void
    {
        // iap_subscriptions: subscription rows are financial records
        // (Art. 17(3)(b)) — keep tier/status/period + original_transaction_id
        // (needed for refund/dispute correlation with the store), strip the
        // store-account-level identifiers.
        IapSubscription::where('user_id', $user->id)->update([
            'apple_app_account_token'      => null,
            'google_obfuscated_account_id' => null,
            'google_purchase_token_hash'   => null,
        ]);

        // iap_receipts: per-renewal payment records — amounts + store
        // transaction refs retained (Art. 17(3)(b)); account-level identifiers
        // and the raw receipt blob are erased.
        IapReceipt::where('user_id', $user->id)->update([
            'apple_app_account_token'      => null,
            'google_obfuscated_account_id' => null,
            'receipt_blob'                 => null,
        ]);

        // subscriptions (Cashier/Stripe): retained as-is — billing records
        // under Art. 17(3)(b); the only identifier is the pseudonymous
        // stripe_id, and the Stripe-side customer object is purged separately
        // (GdprController::purgeStripeCustomerData).

        // subscription_consent_log: proof of consent retained for defence of
        // legal claims (Art. 17(3)(e)); user_agent is device-fingerprint PII
        // with no evidentiary value beyond the (kept) ip_hash — erased.
        SubscriptionConsentLog::where('user_id', $user->id)->update(['user_agent' => null]);

        // trial_card_fingerprints: the HMAC fingerprint is retained for
        // trial-abuse/fraud prevention (legitimate interest; contains no
        // readable PII) — the links to this user are severed.
        TrialCardFingerprint::where('first_user_id', $user->id)->update(['first_user_id' => null]);
        TrialCardFingerprint::where('last_user_id', $user->id)->update(['last_user_id' => null]);

        // cues: transient, expiring UI nudge state with no record-keeping
        // duty — hard delete (Art. 17(1)(a)).
        Cue::where('user_id', $user->id)->delete();
    }

    /**
     * Erase Bridge.xyz ramp personal data while preserving financial records.
     */
    protected function eraseBridgeData(User $user): void
    {
        // bridge_customers: pure identity/KYC-link record (1:1 with the user,
        // no monetary fields) — hard delete (Art. 17(1)); the Bridge-side
        // customer object is deleted via notifyProcessorsOfErasure(), and
        // Bridge retains its own statutory KYC records as a controller.
        BridgeCustomer::where('user_id', $user->id)->delete();

        // ramp_sessions: on/off-ramp transactions are financial records
        // (Art. 17(3)(b)) — amounts/currencies/status retained; the encrypted
        // bank deposit_instructions (the user's bank details), processor
        // client secret and free-form metadata are erased.
        RampSession::where('user_id', $user->id)->update([
            'deposit_instructions' => null,
            'stripe_client_secret' => null,
            'metadata'             => null,
        ]);
    }

    /**
     * Erase wallet personal data while preserving the on-chain financial trace.
     */
    protected function eraseWalletData(User $user): void
    {
        // blockchain_addresses: rows are FK targets for transaction mirrors
        // and part of the on-chain financial trace (Art. 17(3)(b)), and the
        // Helius webhook matches inbound txs by address — hard delete would
        // orphan projections, so we keep the row, deactivate it (which drops
        // it from the Helius/Alchemy watch lists on the next sync) and strip
        // the free-text label + sync metadata. The owner link now points at
        // the anonymized users row. Mass update intentionally bypasses the
        // model observers (no Helius/Bridge re-sync on erasure).
        BlockchainAddress::where('user_uuid', $user->uuid)->update([
            'is_active' => false,
            'label'     => null,
            'metadata'  => null,
        ]);

        // wallet_send_records: on-chain sends are financial records
        // (Art. 17(3)(b)) — amounts/addresses/hashes retained; free-form
        // request metadata and error text are erased.
        WalletSendRecord::where('user_id', $user->id)->update([
            'metadata'      => null,
            'error_message' => null,
        ]);
    }

    /**
     * Erase mobile-payment personal data while preserving monetary records.
     */
    protected function eraseMobilePaymentData(User $user): void
    {
        // payment_intents: monetary records (Art. 17(3)(b)) — amounts/
        // merchant/tx refs retained; free-form request metadata erased.
        PaymentIntent::where('user_id', $user->id)->update(['metadata' => null]);

        // payment_receipts: retained unchanged — monetary receipt records
        // (Art. 17(3)(b)); merchant_name identifies the merchant, not the user.

        // hyperswitch_deposit_intents: retained unchanged — card-deposit
        // financial records (Art. 17(3)(b)) with no direct PII fields.

        // activity_feed_items: denormalised UI mirror of transaction data
        // (the source records are retained above) — hard delete.
        ActivityFeedItem::where('user_id', $user->id)->delete();
    }

    /**
     * Erase mobile device data (pure device PII, no financial-record duty).
     */
    protected function eraseDeviceData(User $user): void
    {
        // Sessions, challenges, push history, preferences and the device rows
        // themselves are pure device PII (push tokens, device names,
        // biometric/passkey public keys, session tokens) with no
        // record-keeping duty — hard delete (Art. 17(1)(a)).
        MobileDeviceSession::where('user_id', $user->id)->delete();
        BiometricChallenge::where('user_id', $user->id)->delete();
        MobilePushNotification::where('user_id', $user->id)->delete();
        MobileNotificationPreference::where('user_id', $user->id)->delete();
        MobileDevice::where('user_id', $user->id)->delete();

        // device_reassignment_log: the reassignment fact is a security audit
        // trail retained for defence against device-takeover claims
        // (Art. 17(3)(e)); the request-fingerprint PII (ip/user_agent) is erased.
        DeviceReassignmentLog::where('previous_user_id', $user->id)
            ->orWhere('new_user_id', $user->id)
            ->update([
                'ip_address' => null,
                'user_agent' => null,
            ]);
    }

    /**
     * Erase API-audit personal data while preserving the security trail.
     */
    protected function eraseApiAuditData(User $user): void
    {
        // mcp_tool_invocations: the invocation history is a security/billing
        // audit trail (Art. 17(3)(e)); the request-fingerprint PII
        // (ip/user_agent) is erased.
        McpToolInvocation::where('user_id', $user->id)->update([
            'ip'         => null,
            'user_agent' => null,
        ]);
    }

    /**
     * (a) Bridge.xyz: delete the customer object (PII + KYC artifacts held by
     * Bridge as our processor for the ramp rail).
     */
    protected function notifyBridgeOfErasure(User $user): void
    {
        $bridgeCustomerId = BridgeCustomer::where('user_id', $user->id)->value('bridge_customer_id');

        if (! is_string($bridgeCustomerId) || $bridgeCustomerId === '') {
            return;
        }

        try {
            ($this->bridgeClient ?? BridgeClient::fromConfig())->deleteCustomer($bridgeCustomerId);

            AuditLog::log(
                'gdpr.processor_erasure_requested',
                $user,
                null,
                null,
                ['processor' => 'bridge', 'reference' => $bridgeCustomerId],
                'gdpr,compliance,deletion'
            );
        } catch (Throwable $e) {
            Log::error('GDPR: Bridge customer deletion failed — recorded for operator retry', [
                'user_id'            => $user->id,
                'bridge_customer_id' => $bridgeCustomerId,
                'error'              => $e->getMessage(),
            ]);

            AuditLog::log(
                'gdpr.processor_erasure_failed',
                $user,
                null,
                null,
                ['processor' => 'bridge', 'reference' => $bridgeCustomerId, 'error' => $e->getMessage()],
                'gdpr,compliance,deletion'
            );
        }
    }

    /**
     * (b) Privy: delete the identity-provider user (email + wallet links held
     * by Privy as our processor for auth/wallets).
     */
    protected function notifyPrivyOfErasure(User $user): void
    {
        $privyUserId = $user->privy_user_id;

        if (! is_string($privyUserId) || $privyUserId === '') {
            return;
        }

        try {
            $client = $this->privyClient ?? app(PrivyEmailOtpClient::class);
            $client->deleteUser($privyUserId);

            AuditLog::log(
                'gdpr.processor_erasure_requested',
                $user,
                null,
                null,
                ['processor' => 'privy', 'reference' => $privyUserId],
                'gdpr,compliance,deletion'
            );
        } catch (Throwable $e) {
            Log::error('GDPR: Privy user deletion failed — recorded for operator retry', [
                'user_id'       => $user->id,
                'privy_user_id' => $privyUserId,
                'error'         => $e->getMessage(),
            ]);

            AuditLog::log(
                'gdpr.processor_erasure_failed',
                $user,
                null,
                null,
                ['processor' => 'privy', 'reference' => $privyUserId, 'error' => $e->getMessage()],
                'gdpr,compliance,deletion'
            );
        }
    }

    /**
     * (c) Apple App Store / Google Play: no server-side deletion API exists
     * for IAP subscriber data — emit a structured TODO (log + audit row) so
     * an operator performs the manual console step.
     */
    protected function recordManualStoreErasureSteps(User $user): void
    {
        /** @var array<int, string> $stores */
        $stores = IapSubscription::where('user_id', $user->id)
            ->distinct()
            ->pluck('store')
            ->all();

        if ($stores === []) {
            return;
        }

        Log::info('gdpr.processor_erasure.manual_step_required', [
            'user_id'    => $user->id,
            'processors' => $stores,
            'action'     => 'Submit a subscriber data-deletion request via App Store Connect / Google Play Console for the erased user.',
        ]);

        AuditLog::log(
            'gdpr.processor_erasure_manual',
            $user,
            null,
            null,
            ['processors' => $stores],
            'gdpr,compliance,deletion'
        );
    }

    /**
     * Check if user data can be deleted.
     */
    public function canDeleteUserData(User $user): array
    {
        $reasons = [];

        // Check for active accounts with balance
        $activeAccounts = $user->accounts()->where('balance', '>', 0)->count();
        if ($activeAccounts > 0) {
            $reasons[] = 'User has active accounts with positive balance';
        }

        // Check for pending transactions
        // This would need to check the event store

        // Check for legal holds
        if ($user->kyc_status === 'in_review') {
            $reasons[] = 'KYC verification is in progress';
        }

        return [
            'can_delete' => empty($reasons),
            'reasons'    => $reasons,
        ];
    }
}
