<?php

declare(strict_types=1);

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Connectors\OpenBankingConnector;
use App\Domain\Banking\Exceptions\BankOperationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Account verification service supporting micro-deposit and
 * instant Open Banking verification flows.
 */
class AccountVerificationService
{
    private const CACHE_PREFIX = 'account_verification:';

    private const MICRO_DEPOSIT_EXPIRY_HOURS = 72;

    private const INSTANT_VERIFICATION_EXPIRY_MINUTES = 30;

    private const MAX_VERIFICATION_ATTEMPTS = 5;

    private ?OpenBankingConnector $openBankingConnector;

    public function __construct(?OpenBankingConnector $openBankingConnector = null)
    {
        $this->openBankingConnector = $openBankingConnector;
    }

    /**
     * Initiate micro-deposit verification by generating two small random amounts.
     *
     * @return array{verification_id: string, status: string, expires_at: string, message: string}
     *
     * @throws BankOperationException
     */
    public function initiateMicroDeposit(string $userId, string $accountId, string $iban, string $currency = 'EUR'): array
    {
        $this->ensureNotProduction();

        // Check for existing active verification
        $existingKey = self::CACHE_PREFIX . "active:{$userId}:{$accountId}";

        /** @var string|null $existingId */
        $existingId = Cache::get($existingKey);

        if ($existingId !== null) {
            $existingData = $this->getVerificationData($existingId);
            if ($existingData !== null && $existingData['status'] === 'pending') {
                throw new BankOperationException(
                    'A micro-deposit verification is already pending for this account.'
                );
            }
        }

        // Generate two small random amounts between 0.01 and 0.99
        $amount1 = random_int(1, 99);
        $amount2 = random_int(1, 99);

        $verificationId = 'mdv_' . Str::uuid()->toString();
        $expiresAt = now()->addHours(self::MICRO_DEPOSIT_EXPIRY_HOURS);

        $verificationData = [
            'verification_id' => $verificationId,
            'user_id'         => $userId,
            'account_id'      => $accountId,
            'iban'            => $iban,
            'currency'        => $currency,
            'method'          => 'micro_deposit',
            'status'          => 'pending',
            'amounts'         => [$amount1, $amount2],
            'attempts'        => 0,
            'created_at'      => now()->toIso8601String(),
            'expires_at'      => $expiresAt->toIso8601String(),
        ];

        $cacheKey = self::CACHE_PREFIX . $verificationId;
        Cache::put($cacheKey, $verificationData, $expiresAt);

        // Track active verification for this user/account pair
        Cache::put($existingKey, $verificationId, $expiresAt);

        Log::info('Micro-deposit verification initiated', [
            'verification_id' => $verificationId,
            'user_id'         => $userId,
            'account_id'      => $accountId,
        ]);

        return [
            'verification_id' => $verificationId,
            'status'          => 'pending',
            'expires_at'      => $expiresAt->toIso8601String(),
            'message'         => "Two micro-deposits in {$currency} will be sent to the account. Please confirm the amounts to verify ownership.",
        ];
    }

    /**
     * Verify micro-deposit amounts submitted by the user.
     *
     * @param  array{int, int}  $submittedAmounts  Two amounts in cents
     * @return array{verification_id: string, status: string, verified: bool, message: string}
     *
     * @throws BankOperationException
     */
    public function verifyMicroDeposit(string $verificationId, array $submittedAmounts): array
    {
        $data = $this->getVerificationData($verificationId);

        if ($data === null) {
            throw new BankOperationException('Verification not found or expired.');
        }

        if ($data['status'] !== 'pending') {
            throw new BankOperationException(
                "Verification is not in pending state. Current status: {$data['status']}"
            );
        }

        $attempts = (int) $data['attempts'] + 1;

        if ($attempts > self::MAX_VERIFICATION_ATTEMPTS) {
            $this->updateVerificationStatus($verificationId, $data, 'failed');

            return [
                'verification_id' => $verificationId,
                'status'          => 'failed',
                'verified'        => false,
                'message'         => 'Maximum verification attempts exceeded.',
            ];
        }

        // Sort both arrays for comparison (order doesn't matter)
        /** @var array{int, int} $expectedAmounts */
        $expectedAmounts = $data['amounts'];
        $expected = $expectedAmounts;
        sort($expected);

        $submitted = array_map('intval', $submittedAmounts);
        sort($submitted);

        $data['attempts'] = $attempts;

        if ($expected === $submitted) {
            $this->updateVerificationStatus($verificationId, $data, 'verified');

            Log::info('Micro-deposit verification successful', [
                'verification_id' => $verificationId,
                'user_id'         => $data['user_id'],
            ]);

            return [
                'verification_id' => $verificationId,
                'status'          => 'verified',
                'verified'        => true,
                'message'         => 'Account successfully verified via micro-deposits.',
            ];
        }

        // Save updated attempt count
        $cacheKey = self::CACHE_PREFIX . $verificationId;
        Cache::put($cacheKey, $data, now()->parse($data['expires_at']));

        $remainingAttempts = self::MAX_VERIFICATION_ATTEMPTS - $attempts;

        Log::warning('Micro-deposit verification failed attempt', [
            'verification_id'    => $verificationId,
            'attempt'            => $attempts,
            'remaining_attempts' => $remainingAttempts,
        ]);

        return [
            'verification_id' => $verificationId,
            'status'          => 'pending',
            'verified'        => false,
            'message'         => "Amounts do not match. {$remainingAttempts} attempts remaining.",
        ];
    }

    /**
     * Initiate instant verification via Open Banking consent.
     *
     * @return array{verification_id: string, status: string, redirect_url: string|null, expires_at: string}
     *
     * @throws BankOperationException
     */
    public function initiateInstantVerification(string $userId, string $accountId, string $iban): array
    {
        $this->ensureNotProduction();

        if ($this->openBankingConnector === null) {
            throw new BankOperationException(
                'Open Banking connector is required for instant verification.'
            );
        }

        $verificationId = 'ivf_' . Str::uuid()->toString();
        $expiresAt = now()->addMinutes(self::INSTANT_VERIFICATION_EXPIRY_MINUTES);

        // Create Open Banking consent for account access
        $this->openBankingConnector->authenticate();
        $consent = $this->openBankingConnector->createConsent([$accountId]);

        $verificationData = [
            'verification_id' => $verificationId,
            'user_id'         => $userId,
            'account_id'      => $accountId,
            'iban'            => $iban,
            'method'          => 'instant_open_banking',
            'status'          => 'awaiting_consent',
            'consent_id'      => $consent['consent_id'],
            'redirect_url'    => $consent['redirect_url'],
            'attempts'        => 0,
            'created_at'      => now()->toIso8601String(),
            'expires_at'      => $expiresAt->toIso8601String(),
        ];

        $cacheKey = self::CACHE_PREFIX . $verificationId;
        Cache::put($cacheKey, $verificationData, $expiresAt);

        // Track active verification
        $activeKey = self::CACHE_PREFIX . "active:{$userId}:{$accountId}";
        Cache::put($activeKey, $verificationId, $expiresAt);

        Log::info('Instant verification initiated via Open Banking', [
            'verification_id' => $verificationId,
            'user_id'         => $userId,
            'consent_id'      => $consent['consent_id'],
        ]);

        return [
            'verification_id' => $verificationId,
            'status'          => 'awaiting_consent',
            'redirect_url'    => $consent['redirect_url'],
            'expires_at'      => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Complete instant verification after user grants consent.
     *
     * @return array{verification_id: string, status: string, verified: bool, message: string}
     *
     * @throws BankOperationException
     */
    public function completeInstantVerification(string $verificationId): array
    {
        $data = $this->getVerificationData($verificationId);

        if ($data === null) {
            throw new BankOperationException('Verification not found or expired.');
        }

        if ($data['method'] !== 'instant_open_banking') {
            throw new BankOperationException('This verification does not use instant Open Banking.');
        }

        if ($this->openBankingConnector === null) {
            throw new BankOperationException('Open Banking connector is required.');
        }

        // Check consent status
        $consentStatus = $this->openBankingConnector->getConsentStatus($data['consent_id']);

        if ($consentStatus === 'valid') {
            // Consent granted — verify the account exists and IBAN matches
            $accounts = $this->openBankingConnector->getAccounts($data['consent_id']);
            $matchingAccount = $accounts->first(
                fn ($account) => $account->iban === $data['iban'] || $account->id === $data['account_id']
            );

            if ($matchingAccount !== null) {
                $this->updateVerificationStatus($verificationId, $data, 'verified');

                Log::info('Instant verification successful', [
                    'verification_id' => $verificationId,
                    'user_id'         => $data['user_id'],
                ]);

                return [
                    'verification_id' => $verificationId,
                    'status'          => 'verified',
                    'verified'        => true,
                    'message'         => 'Account successfully verified via Open Banking.',
                ];
            }

            $this->updateVerificationStatus($verificationId, $data, 'failed');

            return [
                'verification_id' => $verificationId,
                'status'          => 'failed',
                'verified'        => false,
                'message'         => 'Account IBAN does not match any accounts under the consent.',
            ];
        }

        if (in_array($consentStatus, ['rejected', 'revokedByPsu', 'expired'], true)) {
            $this->updateVerificationStatus($verificationId, $data, 'failed');

            return [
                'verification_id' => $verificationId,
                'status'          => 'failed',
                'verified'        => false,
                'message'         => "Consent was {$consentStatus}. Verification cannot proceed.",
            ];
        }

        // Still waiting for user to authorize
        return [
            'verification_id' => $verificationId,
            'status'          => 'awaiting_consent',
            'verified'        => false,
            'message'         => 'Waiting for user to authorize the consent.',
        ];
    }

    /**
     * Get verification status.
     *
     * @return array{verification_id: string, status: string, method: string, created_at: string, expires_at: string, attempts: int}
     *
     * @throws BankOperationException
     */
    public function getVerificationStatus(string $verificationId): array
    {
        $data = $this->getVerificationData($verificationId);

        if ($data === null) {
            throw new BankOperationException('Verification not found or expired.');
        }

        return [
            'verification_id' => $data['verification_id'],
            'status'          => $data['status'],
            'method'          => $data['method'],
            'created_at'      => $data['created_at'],
            'expires_at'      => $data['expires_at'],
            'attempts'        => (int) $data['attempts'],
        ];
    }

    /**
     * Retrieve verification data from cache.
     *
     * @return array<string, mixed>|null
     */
    private function getVerificationData(string $verificationId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $verificationId;

        /** @var array<string, mixed>|null $data */
        $data = Cache::get($cacheKey);

        return $data;
    }

    /**
     * Update verification status in cache.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateVerificationStatus(string $verificationId, array $data, string $status): void
    {
        $data['status'] = $status;
        $data['updated_at'] = now()->toIso8601String();

        $cacheKey = self::CACHE_PREFIX . $verificationId;
        $expiresAt = now()->parse($data['expires_at']);

        Cache::put($cacheKey, $data, $expiresAt);
    }

    /**
     * Guard: disallow execution in production.
     *
     * @throws BankOperationException
     */
    private function ensureNotProduction(): void
    {
        if (app()->environment('production')) {
            throw new BankOperationException(
                'Account verification service is not available in production mode.'
            );
        }
    }
}
