<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Auth\Exceptions\PrivyEmailOtpException;
use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Domain\Compliance\Kyc\Models\BridgeCustomer;
use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Compliance\Models\KycDocument;
use App\Domain\Compliance\Services\GdprService;
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
use App\Models\RampSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription as CashierSubscription;
use Mockery\MockInterface;

/**
 * Bind a Mockery PrivyEmailOtpClient into the container (app()->instance()
 * instead of $this->mock() so PHPStan resolves the mock type — same pattern
 * as tests/Feature/Web/PrivyWebLoginTest.php).
 */
function gdpr_mock_privy_client(): PrivyEmailOtpClient&MockInterface
{
    /** @var PrivyEmailOtpClient&MockInterface $privy */
    $privy = Mockery::mock(PrivyEmailOtpClient::class);
    app()->instance(PrivyEmailOtpClient::class, $privy);

    return $privy;
}

/**
 * Seed one row in every post-v7.13 user-data table so export/erasure
 * behaviour can be asserted per table.
 *
 * @return array<string, mixed>
 */
function gdpr_seed_user_data_graph(User $user): array
{
    $user->update([
        'privy_user_id'   => 'did:privy:gdpr-test',
        'privy_linked_at' => now(),
    ]);

    $stripeSubscription = CashierSubscription::query()->forceCreate([
        'user_id'       => $user->id,
        'type'          => 'default',
        'stripe_id'     => 'sub_gdpr_123',
        'stripe_status' => 'active',
    ]);

    $iapSubscription = IapSubscription::create([
        'user_id'                      => $user->id,
        'store'                        => 'apple',
        'tier'                         => 'pro',
        'status'                       => 'active',
        'original_transaction_id'      => 'orig-tx-1',
        'apple_app_account_token'      => 'apple-token-1',
        'google_obfuscated_account_id' => 'goog-acct-1',
        'google_purchase_token_hash'   => 'goog-hash-1',
    ]);

    $iapReceipt = IapReceipt::create([
        'user_id'                 => $user->id,
        'iap_subscription_id'     => $iapSubscription->id,
        'store'                   => 'apple',
        'product_id'              => 'pro.monthly',
        'tier'                    => 'pro',
        'amount_smallest_unit'    => 999,
        'amount_decimals'         => 2,
        'amount_currency'         => 'EUR',
        'apple_app_account_token' => 'apple-token-1',
        'receipt_blob'            => 'raw-receipt-blob',
    ]);

    $consentLog = SubscriptionConsentLog::create([
        'user_id'         => $user->id,
        'consent_text'    => 'I consent to immediate service and waive withdrawal.',
        'consent_version' => 1,
        'shown_at'        => now(),
        'accepted_at'     => now(),
        'ip_hash'         => 'hashed-ip',
        'user_agent'      => 'ZeltaApp/1.0 (iPhone)',
    ]);

    $fingerprint = TrialCardFingerprint::create([
        'fingerprint_hash' => 'fp-hash-gdpr',
        'first_user_id'    => $user->id,
        'last_user_id'     => $user->id,
        'first_used_at'    => now(),
        'last_used_at'     => now(),
        'trial_user_count' => 1,
    ]);

    $cue = Cue::create([
        'user_id'         => $user->id,
        'kind'            => 'trial_ending',
        'priority'        => 'normal',
        'due_at'          => now(),
        'expires_at'      => now()->addDay(),
        'payload'         => ['days_left' => 2],
        'idempotency_key' => 'cue-idem-1',
        'created_at'      => now(),
    ]);

    $bridgeCustomer = BridgeCustomer::create([
        'user_id'                 => $user->id,
        'bridge_customer_id'      => 'cust_abc123',
        'kyc_status'              => 'approved',
        'virtual_account_id'      => 'va_1',
        'virtual_account_details' => ['bank_name' => 'Lead Bank', 'account_number' => '123456789'],
    ]);

    $rampSession = RampSession::create([
        'user_id'              => $user->id,
        'provider'             => 'bridge',
        'type'                 => 'on',
        'fiat_currency'        => 'EUR',
        'fiat_amount'          => 100.50,
        'crypto_currency'      => 'USDC',
        'status'               => 'completed',
        'wallet_address'       => '0xabc123',
        'deposit_instructions' => ['iban' => 'DE89370400440532013000'],
        'stripe_client_secret' => 'cs_secret_value',
        'metadata'             => ['note' => 'first deposit'],
    ]);

    $address = BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => 'So1anaAddre55',
        'public_key' => 'So1anaAddre55',
        'label'      => 'Main wallet',
        'is_active'  => true,
        'metadata'   => ['helius_synced' => true],
    ]);

    $send = WalletSendRecord::create([
        'public_id'         => 'send_gdpr_1',
        'user_id'           => $user->id,
        'network'           => 'SOLANA',
        'asset'             => 'USDC',
        'amount'            => '1.5000',
        'sender_address'    => 'So1anaAddre55',
        'recipient_address' => 'So1anaRecipient',
        'status'            => 'confirmed',
        'tx_hash'           => 'sig-abc-123',
        'metadata'          => ['device_id' => 'dev-1'],
        'error_message'     => 'transient failure detail',
    ]);

    $merchant = App\Domain\Commerce\Models\Merchant::create([
        'public_id'         => 'merch_gdpr_1',
        'display_name'      => 'Coffee Corner',
        'accepted_assets'   => ['USDC'],
        'accepted_networks' => ['SOLANA'],
    ]);

    $paymentIntent = PaymentIntent::create([
        'public_id'   => 'pi_gdpr_1',
        'user_id'     => $user->id,
        'merchant_id' => $merchant->id,
        'asset'       => 'USDC',
        'network'     => 'SOLANA',
        'amount'      => '2.5000',
        'status'      => 'confirmed',
        'metadata'    => ['table' => '12'],
        'expires_at'  => now()->addMinutes(15),
    ]);

    $paymentReceipt = PaymentReceipt::create([
        'public_id'      => 'rcpt_gdpr_1',
        'user_id'        => $user->id,
        'merchant_name'  => 'Coffee Corner',
        'amount'         => '2.5000',
        'asset'          => 'USDC',
        'network'        => 'SOLANA',
        'tx_hash'        => 'sig-abc-123',
        'network_fee'    => '0.0001',
        'share_token'    => 'share-tok-1',
        'transaction_at' => now(),
    ]);

    $cardDeposit = HyperSwitchDepositIntent::create([
        'hyperswitch_payment_id' => 'hs_pay_1',
        'deposit_uuid'           => (string) Str::uuid(),
        'account_uuid'           => (string) Str::uuid(),
        'user_uuid'              => $user->uuid,
        'amount_cents'           => 1000,
        'currency'               => 'EUR',
        'status'                 => 'completed',
    ]);

    $activityItem = ActivityFeedItem::create([
        'user_id'       => $user->id,
        'activity_type' => 'transfer_in',
        'amount'        => '1.0000',
        'asset'         => 'USDC',
        'network'       => 'SOLANA',
        'status'        => 'completed',
        'occurred_at'   => now(),
    ]);

    $device = MobileDevice::create([
        'user_id'              => $user->id,
        'device_id'            => 'gdpr-device-1',
        'platform'             => 'ios',
        'app_version'          => '1.0.0',
        'device_name'          => 'Test iPhone',
        'device_model'         => 'iPhone 17',
        'push_token'           => 'fcm-token-secret',
        'biometric_enabled'    => true,
        'biometric_public_key' => 'biometric-pub-key',
    ]);

    $session = MobileDeviceSession::create([
        'mobile_device_id' => $device->id,
        'user_id'          => $user->id,
        'session_token'    => 'session-tok-1',
        'ip_address'       => '10.0.0.1',
        'last_activity_at' => now(),
        'expires_at'       => now()->addDay(),
    ]);

    $challenge = BiometricChallenge::create([
        'mobile_device_id' => $device->id,
        'user_id'          => $user->id,
        'challenge'        => 'challenge-bytes',
        'status'           => 'pending',
        'expires_at'       => now()->addMinutes(5),
    ]);

    $push = MobilePushNotification::create([
        'user_id'           => $user->id,
        'mobile_device_id'  => $device->id,
        'notification_type' => 'transaction',
        'title'             => 'You received 1 USDC',
        'body'              => 'From So1anaAddre55',
    ]);

    $preference = MobileNotificationPreference::create([
        'user_id'           => $user->id,
        'mobile_device_id'  => $device->id,
        'notification_type' => 'transaction',
        'push_enabled'      => true,
        'email_enabled'     => false,
    ]);

    $reassignmentAsNewOwner = DeviceReassignmentLog::create([
        'device_id'             => 'gdpr-device-1',
        'previous_user_id'      => null,
        'new_user_id'           => $user->id,
        'had_bound_credentials' => false,
        'reason'                => DeviceReassignmentLog::REASON_AUTO_PUSH_ONLY,
        'ip_address'            => '10.0.0.1',
        'user_agent'            => 'ZeltaApp/1.0',
    ]);

    $otherUser = User::factory()->create();
    $reassignmentAsPreviousOwner = DeviceReassignmentLog::create([
        'device_id'             => 'gdpr-device-1',
        'previous_user_id'      => $user->id,
        'new_user_id'           => $otherUser->id,
        'had_bound_credentials' => false,
        'reason'                => DeviceReassignmentLog::REASON_AUTO_PUSH_ONLY,
        'ip_address'            => '10.9.9.9', // belongs to $otherUser, must never be exported to $user
        'user_agent'            => 'OtherUserAgent/1.0',
    ]);

    DB::table('mcp_tool_invocations')->insert([
        'token_id'      => 'tok-1',
        'client_id'     => 'client-1',
        'user_id'       => $user->id,
        'tool_name'     => 'accounts_list',
        'args_hash'     => 'args-hash-1',
        'result_status' => 'success',
        'ip'            => '10.0.0.1',
        'user_agent'    => 'mcp-client/1.0',
        'created_at'    => now(),
    ]);

    return [
        'stripe_subscription' => $stripeSubscription,
        'iap_subscription'    => $iapSubscription,
        'iap_receipt'         => $iapReceipt,
        'consent_log'         => $consentLog,
        'fingerprint'         => $fingerprint,
        'cue'                 => $cue,
        'bridge_customer'     => $bridgeCustomer,
        'ramp_session'        => $rampSession,
        'address'             => $address,
        'send'                => $send,
        'payment_intent'      => $paymentIntent,
        'payment_receipt'     => $paymentReceipt,
        'card_deposit'        => $cardDeposit,
        'activity_item'       => $activityItem,
        'device'              => $device,
        'session'             => $session,
        'challenge'           => $challenge,
        'push'                => $push,
        'preference'          => $preference,
        'reassignment_new'    => $reassignmentAsNewOwner,
        'reassignment_prev'   => $reassignmentAsPreviousOwner,
        'other_user'          => $otherUser,
    ];
}

beforeEach(function () {
    Storage::fake('private');
    $this->gdprService = app(GdprService::class);
    $this->user = User::factory()->create([
        'name'                       => 'Test User',
        'email'                      => 'test@example.com',
        'privacy_policy_accepted_at' => now()->subMonth(),
        'terms_accepted_at'          => now()->subMonth(),
        'marketing_consent_at'       => now()->subMonth(),
        'data_retention_consent'     => true,
    ]);
});

test('can export user data', function () {
    // Authenticate the user
    Auth::login($this->user);

    // Create some data for the user
    $account = Account::factory()->forUser($this->user)->create();
    $kycDoc = KycDocument::factory()->create(['user_uuid' => $this->user->uuid]);

    $data = $this->gdprService->exportUserData($this->user);

    expect($data)->toHaveKeys(['user', 'accounts', 'transactions', 'kyc_documents', 'audit_logs', 'consents']);

    // Check user data
    expect($data['user']['uuid'])->toBe($this->user->uuid);
    expect($data['user']['name'])->toBe('Test User');
    expect($data['user']['email'])->toBe('test@example.com');

    // Check accounts data
    expect($data['accounts'])->toHaveCount(1);
    expect($data['accounts'][0]['uuid'])->toBe((string) $account->uuid);

    // Check KYC data
    expect($data['kyc_documents'])->toHaveCount(1);
    expect($data['kyc_documents'][0]['id'])->toBe((string) $kycDoc->id);

    // Check consents
    expect($data['consents']['privacy_policy_accepted_at'])->not->toBeNull();
    expect($data['consents']['data_retention_consent'])->toBeTrue();

    // Check audit log created
    $log = AuditLog::where('action', 'gdpr.data_exported')->first();
    expect($log)->not->toBeNull();
    expect($log->user_uuid)->toBe($this->user->uuid);
});

test('can update consent preferences', function () {
    Auth::login($this->user);

    $this->gdprService->updateConsent($this->user, [
        'marketing'      => false,
        'data_retention' => false,
        'privacy_policy' => true,
        'terms'          => true,
    ]);

    $this->user->refresh();
    expect($this->user->marketing_consent_at)->toBeNull();
    expect($this->user->data_retention_consent)->toBeFalse();
    expect($this->user->privacy_policy_accepted_at)->not->toBeNull();
    expect($this->user->terms_accepted_at)->not->toBeNull();

    // Check audit log
    $log = AuditLog::where('action', 'gdpr.consent_updated')->first();
    expect($log)->not->toBeNull();
    expect($log->old_values)->toHaveKey('marketing_consent');
    expect($log->new_values)->toHaveKey('marketing');
});

test('can check if user data can be deleted', function () {
    // User with no balance - can delete
    $account = Account::factory()->forUser($this->user)->create(['balance' => 0]);
    $check = $this->gdprService->canDeleteUserData($this->user);
    expect($check['can_delete'])->toBeTrue();
    expect($check['reasons'])->toBeEmpty();

    // User with positive balance - cannot delete
    $account->update(['balance' => 10000]);
    $check = $this->gdprService->canDeleteUserData($this->user);
    expect($check['can_delete'])->toBeFalse();
    expect($check['reasons'])->toContain('User has active accounts with positive balance');

    // User with KYC in review - cannot delete
    $account->update(['balance' => 0]);
    $this->user->update(['kyc_status' => 'in_review']);
    $check = $this->gdprService->canDeleteUserData($this->user);
    expect($check['can_delete'])->toBeFalse();
    expect($check['reasons'])->toContain('KYC verification is in progress');
});

test('can anonymize user data', function () {
    Auth::login($this->user);

    $originalName = $this->user->name;
    $originalEmail = $this->user->email;
    $originalUuid = $this->user->uuid;

    // Create KYC document
    $kycDoc = KycDocument::factory()->create([
        'user_uuid' => $this->user->uuid,
        'file_path' => 'kyc/' . $this->user->uuid . '/document.pdf',
    ]);
    Storage::disk('private')->put($kycDoc->file_path, 'fake content');

    $this->gdprService->deleteUserData($this->user, [
        'delete_documents'       => true,
        'anonymize_transactions' => true,
    ]);

    $this->user->refresh();

    // Check user is anonymized
    expect($this->user->name)->toStartWith('ANONYMIZED_');
    expect($this->user->email)->toBe('deleted-' . $originalUuid . '@anonymized.local');
    expect($this->user->kyc_data)->toBeNull();

    // Check KYC documents deleted
    expect(KycDocument::where('user_uuid', $this->user->uuid)->count())->toBe(0);
    Storage::disk('private')->assertMissing($kycDoc->file_path);

    // Check audit logs
    $deletionLog = AuditLog::where('action', 'gdpr.deletion_requested')->first();
    expect($deletionLog)->not->toBeNull();

    $anonymizationLog = AuditLog::where('action', 'gdpr.transactions_anonymized')->first();
    expect($anonymizationLog)->not->toBeNull();
});

test('consent tracking works correctly', function () {
    $user = User::factory()->create([
        'privacy_policy_accepted_at' => null,
        'terms_accepted_at'          => null,
        'marketing_consent_at'       => null,
        'data_retention_consent'     => false,
    ]);

    Auth::login($user);

    // Initially no consents
    expect($user->privacy_policy_accepted_at)->toBeNull();
    expect($user->terms_accepted_at)->toBeNull();
    expect($user->marketing_consent_at)->toBeNull();
    expect($user->data_retention_consent)->toBeFalse();

    // Update consents
    $this->gdprService->updateConsent($user, [
        'privacy_policy' => true,
        'terms'          => true,
        'marketing'      => true,
        'data_retention' => true,
    ]);

    $user->refresh();
    expect($user->privacy_policy_accepted_at)->not->toBeNull();
    expect($user->terms_accepted_at)->not->toBeNull();
    expect($user->marketing_consent_at)->not->toBeNull();
    expect($user->data_retention_consent)->toBeTrue();

    // Revoke marketing consent
    $this->gdprService->updateConsent($user, ['marketing' => false]);

    $user->refresh();
    expect($user->marketing_consent_at)->toBeNull();
    expect($user->privacy_policy_accepted_at)->not->toBeNull(); // Others unchanged
});

test('export includes all post-v7.13 user-data sections with deliberate field choices', function () {
    Auth::login($this->user);
    gdpr_seed_user_data_graph($this->user);

    $data = $this->gdprService->exportUserData($this->user->refresh());

    expect($data)->toHaveKeys([
        'user', 'accounts', 'transactions', 'kyc_documents', 'audit_logs', 'consents',
        'subscriptions', 'bridge', 'wallet', 'mobile_payments', 'devices', 'api_usage',
    ]);

    // Identity-provider link is included in the user section
    expect($data['user']['privy_user_id'])->toBe('did:privy:gdpr-test');

    // Subscriptions: Stripe + IAP metadata included, processor refs/pseudonyms omitted
    expect($data['subscriptions']['stripe'])->toHaveCount(1);
    expect($data['subscriptions']['stripe'][0]['stripe_status'])->toBe('active');
    expect($data['subscriptions']['stripe'][0])->not->toHaveKey('stripe_id');
    expect($data['subscriptions']['iap'])->toHaveCount(1);
    expect($data['subscriptions']['iap'][0]['store'])->toBe('apple');
    expect($data['subscriptions']['iap'][0])->not->toHaveKey('apple_app_account_token');
    expect($data['subscriptions']['iap'][0])->not->toHaveKey('google_purchase_token_hash');
    expect($data['subscriptions']['iap_payments'][0]['amount_smallest_unit'])->toBe(999);
    expect($data['subscriptions']['iap_payments'][0])->not->toHaveKey('receipt_blob');
    expect($data['subscriptions']['consent_log'][0]['consent_version'])->toBe(1);
    expect($data['subscriptions']['consent_log'][0])->not->toHaveKey('ip_hash');

    // Bridge: virtual account + deposit instructions are the user's own bank
    // details and are exported decrypted; processor secrets are omitted
    expect($data['bridge']['customer']['bridge_customer_id'])->toBe('cust_abc123');
    expect($data['bridge']['customer']['virtual_account_details'])
        ->toBe(['bank_name' => 'Lead Bank', 'account_number' => '123456789']);
    expect($data['bridge']['customer'])->not->toHaveKey('kyc_link_url');
    expect($data['bridge']['ramp_sessions'][0]['deposit_instructions'])
        ->toBe(['iban' => 'DE89370400440532013000']);
    expect($data['bridge']['ramp_sessions'][0])->not->toHaveKey('stripe_client_secret');

    // Wallet: addresses + sends, internal sync/processing fields omitted
    expect($data['wallet']['blockchain_addresses'][0]['address'])->toBe('So1anaAddre55');
    expect($data['wallet']['blockchain_addresses'][0])->not->toHaveKey('metadata');
    expect($data['wallet']['sends'][0]['tx_hash'])->toBe('sig-abc-123');
    expect($data['wallet']['sends'][0])->not->toHaveKey('metadata');

    // Mobile payments
    expect($data['mobile_payments']['payment_intents'][0]['status'])->toBe('confirmed');
    expect($data['mobile_payments']['payment_receipts'][0]['merchant_name'])->toBe('Coffee Corner');
    expect($data['mobile_payments']['card_deposits'][0]['amount_cents'])->toBe(1000);

    // Devices: descriptive data exported, credentials (push token, biometric keys) omitted
    expect($data['devices']['devices'][0]['device_name'])->toBe('Test iPhone');
    expect($data['devices']['devices'][0])->not->toHaveKey('push_token');
    expect($data['devices']['devices'][0])->not->toHaveKey('biometric_public_key');
    expect($data['devices']['notification_preferences'][0]['notification_type'])->toBe('transaction');

    // Reassignment rows: ip/user_agent only disclosed where this user is the
    // registrant — never another data subject's request fingerprint
    $reassignments = collect($data['devices']['device_reassignments']);
    $asNewOwner = $reassignments->firstWhere('role', 'new_owner');
    $asPreviousOwner = $reassignments->firstWhere('role', 'previous_owner');
    expect($asNewOwner['ip_address'])->toBe('10.0.0.1');
    expect($asPreviousOwner['ip_address'])->toBeNull();
    expect($asPreviousOwner['user_agent'])->toBeNull();

    // API usage
    expect($data['api_usage']['mcp_tool_invocations'][0]['tool_name'])->toBe('accounts_list');
    expect($data['api_usage']['mcp_tool_invocations'][0])->not->toHaveKey('args_hash');
});

test('erasure applies the per-table legal action to every post-v7.13 table', function () {
    Auth::login($this->user);
    $refs = gdpr_seed_user_data_graph($this->user);

    // Fan-out targets are stubbed: Bridge over fake HTTP, Privy via mock
    Http::fake(['https://api.bridge.xyz/*' => Http::response(['deleted' => true], 200)]);
    gdpr_mock_privy_client()
        ->shouldReceive('deleteUser')->once()->with('did:privy:gdpr-test');

    app(GdprService::class)->deleteUserData($this->user->refresh(), [
        'delete_documents'       => true,
        'anonymize_transactions' => true,
    ]);

    $this->user->refresh();

    // users: anonymized + identity-provider link severed
    expect($this->user->name)->toStartWith('ANONYMIZED_');
    expect($this->user->privy_user_id)->toBeNull();
    expect($this->user->privy_linked_at)->toBeNull();

    // bridge_customers: pure identity/KYC link — hard deleted
    expect(BridgeCustomer::where('user_id', $this->user->id)->exists())->toBeFalse();

    // ramp_sessions: financial record kept, PII fields nulled
    $ramp = $refs['ramp_session']->refresh();
    expect($ramp->deposit_instructions)->toBeNull();
    expect($ramp->stripe_client_secret)->toBeNull();
    expect($ramp->metadata)->toBeNull();
    expect($ramp->fiat_currency)->toBe('EUR');
    expect($ramp->status)->toBe('completed');

    // iap_subscriptions: financial record kept, store-account identifiers nulled
    $iap = $refs['iap_subscription']->refresh();
    expect($iap->apple_app_account_token)->toBeNull();
    expect($iap->google_obfuscated_account_id)->toBeNull();
    expect($iap->google_purchase_token_hash)->toBeNull();
    expect($iap->original_transaction_id)->toBe('orig-tx-1'); // kept for refund/dispute correlation
    expect($iap->status)->toBe('active');

    // iap_receipts: amounts kept, account identifiers + raw blob nulled
    $receipt = $refs['iap_receipt']->refresh();
    expect($receipt->apple_app_account_token)->toBeNull();
    expect($receipt->receipt_blob)->toBeNull();
    expect($receipt->amount_smallest_unit)->toBe(999);

    // subscriptions (Cashier): retained unchanged (Art. 17(3)(b))
    expect(CashierSubscription::query()->where('user_id', $this->user->id)->count())->toBe(1);

    // subscription_consent_log: proof of consent kept, user_agent nulled
    $consent = $refs['consent_log']->refresh();
    expect($consent->user_agent)->toBeNull();
    expect($consent->consent_text)->not->toBeNull();

    // trial_card_fingerprints: fraud-prevention hash kept, user links severed
    $fp = $refs['fingerprint']->refresh();
    expect($fp->first_user_id)->toBeNull();
    expect($fp->last_user_id)->toBeNull();
    expect($fp->fingerprint_hash)->toBe('fp-hash-gdpr');

    // cues: transient UI state — hard deleted
    expect(Cue::where('user_id', $this->user->id)->exists())->toBeFalse();

    // blockchain_addresses: row kept (financial trace), deactivated + label/metadata stripped
    $address = $refs['address']->refresh();
    expect($address->is_active)->toBeFalse();
    expect($address->label)->toBeNull();
    expect($address->metadata)->toBeNull();
    expect($address->address)->toBe('So1anaAddre55');

    // wallet_send_records: monetary record kept, metadata/error text nulled
    $send = $refs['send']->refresh();
    expect($send->metadata)->toBeNull();
    expect($send->error_message)->toBeNull();
    expect($send->tx_hash)->toBe('sig-abc-123');

    // payment_intents: monetary record kept, metadata nulled
    $intent = $refs['payment_intent']->refresh();
    expect($intent->metadata)->toBeNull();
    expect($intent->amount)->not->toBeNull();

    // payment_receipts + hyperswitch_deposit_intents: retained unchanged
    expect(PaymentReceipt::where('user_id', $this->user->id)->exists())->toBeTrue();
    expect(HyperSwitchDepositIntent::where('user_uuid', $this->user->uuid)->exists())->toBeTrue();

    // activity_feed_items: denormalised mirror — hard deleted
    expect(ActivityFeedItem::where('user_id', $this->user->id)->exists())->toBeFalse();

    // mobile device PII: hard deleted
    expect(MobileDevice::where('user_id', $this->user->id)->exists())->toBeFalse();
    expect(MobileDeviceSession::where('user_id', $this->user->id)->exists())->toBeFalse();
    expect(BiometricChallenge::where('user_id', $this->user->id)->exists())->toBeFalse();
    expect(MobilePushNotification::where('user_id', $this->user->id)->exists())->toBeFalse();
    expect(MobileNotificationPreference::where('user_id', $this->user->id)->exists())->toBeFalse();

    // device_reassignment_log: audit fact kept, request fingerprint nulled
    $reassignment = $refs['reassignment_new']->refresh();
    expect($reassignment->ip_address)->toBeNull();
    expect($reassignment->user_agent)->toBeNull();
    expect($reassignment->device_id)->toBe('gdpr-device-1');

    // mcp_tool_invocations: audit trail kept, ip/user_agent nulled
    $invocation = DB::table('mcp_tool_invocations')->where('user_id', $this->user->id)->first();
    expect($invocation)->not->toBeNull();
    assert($invocation instanceof stdClass);
    expect($invocation->ip)->toBeNull();
    expect($invocation->user_agent)->toBeNull();
    expect($invocation->tool_name)->toBe('accounts_list');
});

test('processor fan-out calls Bridge and Privy with the right identifiers', function () {
    Auth::login($this->user);

    Http::fake(['https://api.bridge.xyz/*' => Http::response(['deleted' => true], 200)]);

    BridgeCustomer::create([
        'user_id'            => $this->user->id,
        'bridge_customer_id' => 'cust_abc123',
        'kyc_status'         => 'approved',
    ]);
    $this->user->update(['privy_user_id' => 'did:privy:gdpr-test', 'privy_linked_at' => now()]);
    IapSubscription::create([
        'user_id' => $this->user->id,
        'store'   => 'apple',
        'tier'    => 'pro',
        'status'  => 'active',
    ]);

    gdpr_mock_privy_client()
        ->shouldReceive('deleteUser')->once()->with('did:privy:gdpr-test');

    app(GdprService::class)->notifyProcessorsOfErasure($this->user->refresh());

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && $request->url() === 'https://api.bridge.xyz/v0/customers/cust_abc123';
    });

    $requested = AuditLog::where('action', 'gdpr.processor_erasure_requested')->get();
    expect($requested->pluck('metadata.processor')->sort()->values()->all())->toBe(['bridge', 'privy']);

    // Apple/Google have no deletion API — manual runbook step is recorded
    $manual = AuditLog::where('action', 'gdpr.processor_erasure_manual')->first();
    expect($manual)->not->toBeNull();
    assert($manual instanceof AuditLog);
    expect($manual->metadata)->toBe(['processors' => ['apple']]);
});

test('processor fan-out failures are recorded and never abort local erasure', function () {
    Auth::login($this->user);

    Http::fake(['https://api.bridge.xyz/*' => Http::response('upstream exploded', 500)]);

    BridgeCustomer::create([
        'user_id'            => $this->user->id,
        'bridge_customer_id' => 'cust_failing',
        'kyc_status'         => 'approved',
    ]);
    $this->user->update(['privy_user_id' => 'did:privy:failing', 'privy_linked_at' => now()]);

    gdpr_mock_privy_client()
        ->shouldReceive('deleteUser')->once()->with('did:privy:failing')
        ->andThrow(PrivyEmailOtpException::apiError('/api/v1/users/did:privy:failing', 500, 'boom'));

    // Must not throw despite both processors failing
    app(GdprService::class)->deleteUserData($this->user->refresh());

    $this->user->refresh();

    // Local erasure proceeded
    expect($this->user->name)->toStartWith('ANONYMIZED_');
    expect($this->user->privy_user_id)->toBeNull();
    expect(BridgeCustomer::where('user_id', $this->user->id)->exists())->toBeFalse();
    expect(AuditLog::where('action', 'gdpr.deletion_completed')->exists())->toBeTrue();

    // Failures recorded for operator retry, with processor + reference
    $failures = AuditLog::where('action', 'gdpr.processor_erasure_failed')->get();
    expect($failures->pluck('metadata.processor')->sort()->values()->all())->toBe(['bridge', 'privy']);
    $bridgeFailure = $failures->firstWhere('metadata.processor', 'bridge');
    expect($bridgeFailure)->not->toBeNull();
    assert($bridgeFailure instanceof AuditLog);
    expect($bridgeFailure->metadata)->toHaveKey('reference', 'cust_failing');
});

test('a Bridge 404 during fan-out is treated as already-deleted success', function () {
    Auth::login($this->user);

    Http::fake(['https://api.bridge.xyz/*' => Http::response(['error' => 'not found'], 404)]);

    BridgeCustomer::create([
        'user_id'            => $this->user->id,
        'bridge_customer_id' => 'cust_gone',
        'kyc_status'         => 'approved',
    ]);

    app(GdprService::class)->notifyProcessorsOfErasure($this->user->refresh());

    expect(AuditLog::where('action', 'gdpr.processor_erasure_failed')->exists())->toBeFalse();
    $requested = AuditLog::where('action', 'gdpr.processor_erasure_requested')->first();
    expect($requested)->not->toBeNull();
    assert($requested instanceof AuditLog);
    expect($requested->metadata)->toHaveKey('processor', 'bridge');
});
