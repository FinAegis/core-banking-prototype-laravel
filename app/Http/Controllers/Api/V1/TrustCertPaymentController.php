<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\TrustCert\Models\VerificationPayment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrustCertPaymentController extends Controller
{
    /**
     * Verification fee schedule by trust level (numeric).
     *
     * @var array<int, numeric-string>
     */
    private const LEVEL_FEES = [
        1 => '4.99',
        2 => '4.99',
        3 => '9.99',
        4 => '9.99',
    ];

    /**
     * IAP product IDs for each level.
     *
     * @var array<int, string>
     */
    public const IAP_PRODUCT_IDS = [
        1 => 'kyc_verification_level_1',
        2 => 'kyc_verification_level_2',
        3 => 'kyc_verification_level_3',
    ];

    /**
     * Method 1: Pay with wallet balance (USDC internal ledger deduction).
     */
    public function payWallet(string $applicationId, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $application = $this->findApplication($user->id, $applicationId);
        if (! $application) {
            return response()->json([
                'error'   => 'ERR_CERT_404',
                'message' => 'Application not found.',
            ], 404);
        }

        if ($this->isAlreadyPaid($applicationId)) {
            return response()->json([
                'error'   => 'ERR_CERT_409',
                'message' => 'This application has already been paid.',
            ], 409);
        }

        $fee = $this->getFeeForApplication($application);

        // Prototype: simulate wallet balance check
        // In production, integrate with real wallet/balance service
        $availableBalance = $this->getWalletBalance($user->id);

        if (bccomp($availableBalance, $fee, 2) < 0) {
            return response()->json([
                'error'     => 'ERR_CERT_501',
                'message'   => 'Insufficient balance. Please top up your wallet or choose another payment method.',
                'required'  => (float) $fee,
                'available' => (float) $availableBalance,
            ], 402);
        }

        $receiptId = 'rcpt_' . Str::random(12);
        $paidAt = now();

        DB::transaction(function () use ($user, $applicationId, $fee, $receiptId, $paidAt): void {
            VerificationPayment::create([
                'user_id'        => $user->id,
                'application_id' => $applicationId,
                'method'         => 'wallet',
                'amount'         => $fee,
                'currency'       => 'USD',
                'status'         => 'completed',
            ]);

            $this->markApplicationPaid($user->id, $applicationId, 'wallet', $receiptId, $fee, $paidAt);
        });

        return response()->json([
            'receiptId' => $receiptId,
            'amount'    => (float) $fee,
            'currency'  => 'USD',
            'paidAt'    => $paidAt->toIso8601String(),
        ]);
    }

    /**
     * Method 2: Pay with Stripe Checkout (card).
     */
    public function payCard(string $applicationId, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $application = $this->findApplication($user->id, $applicationId);
        if (! $application) {
            return response()->json([
                'error'   => 'ERR_CERT_404',
                'message' => 'Application not found.',
            ], 404);
        }

        if ($this->isAlreadyPaid($applicationId)) {
            return response()->json([
                'error'   => 'ERR_CERT_409',
                'message' => 'This application has already been paid.',
            ], 409);
        }

        $fee = $this->getFeeForApplication($application);
        $level = $application['target_level'] ?? 'basic';

        $stripeSecret = (string) config('services.stripe.secret', '');
        if ($stripeSecret === '') {
            Log::error('TrustCertPayment: STRIPE_SECRET not configured');

            return response()->json([
                'error'   => 'ERR_CERT_500',
                'message' => 'Payment service unavailable. Please try another method.',
            ], 503);
        }

        // Create Stripe Checkout Session
        $response = Http::withToken($stripeSecret)
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode'                                          => 'payment',
                'success_url'                                   => 'zelta://kyc-payment-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'                                    => 'zelta://kyc-payment-cancel',
                'line_items[0][price_data][currency]'           => 'usd',
                'line_items[0][price_data][product_data][name]' => 'KYC Verification - Level ' . ucfirst($level),
                'line_items[0][price_data][unit_amount]'        => (string) ((int) bcmul($fee, '100', 0)),
                'line_items[0][quantity]'                       => '1',
                'metadata[application_id]'                      => $applicationId,
                'metadata[user_id]'                             => (string) $user->id,
                'metadata[level]'                               => $level,
                'customer_email'                                => $user->email,
            ]);

        if (! $response->successful()) {
            Log::error('TrustCertPayment: Stripe session creation failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return response()->json([
                'error'   => 'ERR_CERT_500',
                'message' => 'Failed to create payment session. Please try again.',
            ], 502);
        }

        /** @var array{id: string, url: string, expires_at: int} $session */
        $session = $response->json();

        return response()->json([
            'sessionId'   => $session['id'] ?? '',
            'checkoutUrl' => $session['url'] ?? '',
            'expiresAt'   => isset($session['expires_at'])
                ? date('c', (int) $session['expires_at'])
                : now()->addMinutes(30)->toIso8601String(),
        ]);
    }

    /**
     * Method 3: Pay with In-App Purchase receipt.
     */
    public function payIap(string $applicationId, Request $request): JsonResponse
    {
        if (app()->environment('production')) {
            return response()->json([
                'error'   => 'ERR_CERT_501',
                'message' => 'IAP validation not yet available',
            ], 501);
        }

        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'receipt'  => ['required', 'string'],
            'platform' => ['required', 'string', 'in:ios,android'],
        ]);

        $application = $this->findApplication($user->id, $applicationId);
        if (! $application) {
            return response()->json([
                'error'   => 'ERR_CERT_404',
                'message' => 'Application not found.',
            ], 404);
        }

        if ($this->isAlreadyPaid($applicationId)) {
            return response()->json([
                'error'   => 'ERR_CERT_409',
                'message' => 'This application has already been paid.',
            ], 409);
        }

        $fee = $this->getFeeForApplication($application);
        $platform = $request->input('platform');
        $receipt = $request->input('receipt');

        // Validate receipt (prototype: basic validation)
        // In production, verify against Apple App Store / Google Play APIs
        if (! $this->validateIapReceipt((string) $receipt, (string) $platform)) {
            return response()->json([
                'error'   => 'ERR_CERT_502',
                'message' => 'Receipt validation failed. The purchase receipt is invalid or expired.',
            ], 402);
        }

        $receiptId = 'rcpt_iap_' . Str::random(12);
        $paidAt = now();

        DB::transaction(function () use ($user, $applicationId, $fee, $platform, $receiptId, $paidAt): void {
            VerificationPayment::create([
                'user_id'            => $user->id,
                'application_id'     => $applicationId,
                'method'             => 'iap',
                'amount'             => $fee,
                'currency'           => 'USD',
                'status'             => 'completed',
                'iap_transaction_id' => $receiptId,
                'platform'           => $platform,
            ]);

            $this->markApplicationPaid((int) $user->id, $applicationId, 'iap', $receiptId, $fee, $paidAt);
        });

        return response()->json([
            'receiptId' => $receiptId,
            'amount'    => (float) $fee,
            'currency'  => 'USD',
            'paidAt'    => $paidAt->toIso8601String(),
        ]);
    }

    /**
     * Get the verification fee for an application based on its target level.
     *
     * @param array<string, mixed> $application
     *
     * @return numeric-string
     */
    private function getFeeForApplication(array $application): string
    {
        /** @var array{target_level?: string} $application */
        $targetLevel = $application['target_level'] ?? 'basic';

        $levelMap = [
            'unknown'  => 0,
            'basic'    => 1,
            'verified' => 2,
            'high'     => 3,
            'ultimate' => 4,
        ];

        $numericLevel = $levelMap[$targetLevel] ?? 1;

        return self::LEVEL_FEES[$numericLevel] ?? '4.99';
    }

    /**
     * Check if an application is already paid.
     */
    private function isAlreadyPaid(string $applicationId): bool
    {
        return VerificationPayment::where('application_id', $applicationId)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Get the user's wallet balance (prototype: returns demo balance).
     *
     * @return numeric-string
     */
    private function getWalletBalance(int $userId): string
    {
        // Prototype: use cache-based demo balance
        // In production, query the actual wallet/ledger service
        $balance = Cache::get("wallet_balance:{$userId}");

        if (is_string($balance) && is_numeric($balance)) {
            return bcadd($balance, '0', 2);
        }

        if (is_int($balance) || is_float($balance)) {
            return bcadd((string) $balance, '0', 2);
        }

        // Default demo balance for testing
        return '100.00';
    }

    /**
     * Validate an IAP receipt (prototype: basic validation).
     *
     * In production, verify against:
     * - Apple: POST https://buy.itunes.apple.com/verifyReceipt
     * - Google: GET https://androidpublisher.googleapis.com/...
     */
    private function validateIapReceipt(string $receipt, string $platform): bool
    {
        // Prototype: accept non-empty base64 receipts
        // Real implementation should verify with Apple/Google APIs
        if ($receipt === '' || $platform === '') {
            return false;
        }

        // Allow test/demo receipts in non-production environments
        if (app()->environment('local', 'testing')) {
            return true;
        }

        // In production, this would call Apple/Google verification APIs
        // For now, log and accept (to be replaced with real verification)
        Log::info('TrustCertPayment: IAP receipt validation pending real integration', [
            'platform' => $platform,
        ]);

        return true;
    }

    /**
     * Find an application from cache storage.
     *
     * @return array<string, mixed>|null
     */
    private function findApplication(int $userId, string $applicationId): ?array
    {
        /** @var array<string, mixed>|null $application */
        $application = Cache::get("trustcert_application:{$userId}");

        if (! is_array($application) || ($application['id'] ?? '') !== $applicationId) {
            return null;
        }

        return $application;
    }

    /**
     * Mark an application as paid in cache storage.
     */
    private function markApplicationPaid(
        int $userId,
        string $applicationId,
        string $method,
        string $receiptId,
        string $amount,
        \Illuminate\Support\Carbon $paidAt,
    ): void {
        /** @var array<string, mixed>|null $application */
        $application = Cache::get("trustcert_application:{$userId}");

        if (! is_array($application) || ($application['id'] ?? '') !== $applicationId) {
            return;
        }

        $application['status'] = 'paid';
        $application['paid_at'] = $paidAt->toIso8601String();
        $application['payment_method'] = $method;
        $application['payment_receipt_id'] = $receiptId;
        $application['payment_amount'] = $amount;
        $application['updated_at'] = now()->toIso8601String();

        Cache::put("trustcert_application:{$userId}", $application, now()->addDays(30));
    }

    /**
     * Get verification fee for a given numeric trust level.
     *
     * Used by MobileTrustCertController to add verificationFee to requirements response.
     */
    public static function getVerificationFee(int $numericLevel): ?string
    {
        return self::LEVEL_FEES[$numericLevel] ?? null;
    }
}
