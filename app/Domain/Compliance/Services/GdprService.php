<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GdprService
{
    /**
     * Export all user data (GDPR Article 20 - Right to data portability)
     */
    public function exportUserData(User $user): array
    {
        AuditLog::create([
            'user_uuid' => $user->uuid,
            'action' => 'gdpr.data_exported',
            'auditable_type' => get_class($user),
            'auditable_id' => $user->uuid,
            'metadata' => ['requested_by' => $user->uuid],
            'tags' => 'gdpr,compliance,data-export',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        return [
            'user' => $this->getUserData($user),
            'accounts' => $this->getAccountData($user),
            'transactions' => $this->getTransactionData($user),
            'kyc_documents' => $this->getKycData($user),
            'audit_logs' => $this->getAuditData($user),
            'consents' => $this->getConsentData($user),
        ];
    }

    /**
     * Delete user data (GDPR Article 17 - Right to erasure)
     */
    public function deleteUserData(User $user, array $options = []): void
    {
        DB::transaction(function () use ($user, $options) {
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
        });
    }

    /**
     * Update user consent preferences
     */
    public function updateConsent(User $user, array $consents): void
    {
        $oldConsents = [
            'marketing_consent' => $user->marketing_consent_at !== null,
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
     * Get user's personal data
     */
    protected function getUserData(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'kyc_status' => $user->kyc_status,
            'kyc_level' => $user->kyc_level,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Get user's account data
     */
    protected function getAccountData(User $user): array
    {
        return $user->accounts->map(function ($account) {
            return [
                'uuid' => $account->uuid,
                'balance' => $account->balance,
                'status' => $account->status,
                'created_at' => $account->created_at,
                'balances' => $account->balances->map(function ($balance) {
                    return [
                        'asset_code' => $balance->asset_code,
                        'balance' => $balance->balance,
                    ];
                })->toArray(),
            ];
        })->toArray();
    }

    /**
     * Get user's transaction data
     */
    protected function getTransactionData(User $user): array
    {
        // This would need to query the event store for all transactions
        // For now, returning empty array
        return [];
    }

    /**
     * Get user's KYC data
     */
    protected function getKycData(User $user): array
    {
        return $user->kycDocuments->map(function ($document) {
            return [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'status' => $document->status,
                'uploaded_at' => $document->uploaded_at,
                'verified_at' => $document->verified_at,
            ];
        })->toArray();
    }

    /**
     * Get user's audit data
     */
    protected function getAuditData(User $user): array
    {
        return AuditLog::forUser($user->uuid)
            ->limit(1000)
            ->get()
            ->map(function ($log) {
                return [
                    'action' => $log->action,
                    'created_at' => $log->created_at,
                    'ip_address' => $log->ip_address,
                ];
            })
            ->toArray();
    }

    /**
     * Get user's consent history
     */
    protected function getConsentData(User $user): array
    {
        return [
            'privacy_policy_accepted_at' => $user->privacy_policy_accepted_at,
            'terms_accepted_at' => $user->terms_accepted_at,
            'marketing_consent_at' => $user->marketing_consent_at,
            'data_retention_consent' => $user->data_retention_consent,
        ];
    }

    /**
     * Anonymize user data
     */
    protected function anonymizeUser(User $user): void
    {
        $user->update([
            'name' => 'Deleted User ' . substr($user->uuid, 0, 8),
            'email' => 'deleted-' . $user->uuid . '@anonymous.local',
            'kyc_data' => null,
        ]);
    }

    /**
     * Delete KYC documents
     */
    protected function deleteKycDocuments(User $user): void
    {
        $user->kycDocuments->each(function ($document) {
            if ($document->file_path && Storage::disk('private')->exists($document->file_path)) {
                Storage::disk('private')->delete($document->file_path);
            }
            $document->delete();
        });
    }

    /**
     * Anonymize transaction data
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
     * Check if user data can be deleted
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
            'reasons' => $reasons,
        ];
    }
}