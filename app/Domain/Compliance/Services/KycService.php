<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Compliance\Models\KycDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class KycService
{
    /**
     * Submit KYC documents for a user.
     */
    public function submitKyc(User $user, array $documents): void
    {
        DB::transaction(
            function () use ($user, $documents) {
                // Update user KYC status
                $user->update(
                    [
                        'kyc_status'       => 'pending',
                        'kyc_submitted_at' => now(),
                    ]
                );

                // Store documents
                foreach ($documents as $document) {
                    $this->storeDocument($user, $document);
                }

                // Log the action
                AuditLog::log(
                    'kyc.submitted',
                    $user,
                    null,
                    ['documents'      => count($documents)],
                    ['document_types' => array_column($documents, 'type')],
                    'kyc,compliance'
                );
            }
        );
    }

    /**
     * Store a KYC document.
     */
    protected function storeDocument(User $user, array $documentData): KycDocument
    {
        $path = $documentData['file']->store("kyc/{$user->uuid}", 'private');
        $hash = hash_file('sha256', Storage::disk('private')->path($path));

        return KycDocument::create(
            [
                'user_uuid'     => $user->uuid,
                'document_type' => $documentData['type'],
                'file_path'     => $path,
                'file_hash'     => $hash,
                'uploaded_at'   => now(),
                'metadata'      => [
                    'original_name' => $documentData['file']->getClientOriginalName(),
                    'mime_type'     => $documentData['file']->getMimeType(),
                    'size'          => $documentData['file']->getSize(),
                ],
            ]
        );
    }

    /**
     * Verify user KYC.
     */
    public function verifyKyc(User $user, string $verifiedBy, array $options = []): void
    {
        DB::transaction(
            function () use ($user, $verifiedBy, $options) {
                $oldStatus = $user->kyc_status;

                // Update user status
                $user->update(
                    [
                        'kyc_status'      => 'approved',
                        'kyc_approved_at' => now(),
                        'kyc_expires_at'  => $options['expires_at'] ?? now()->addYears(2),
                        'kyc_level'       => $options['level'] ?? 'enhanced',
                        'risk_rating'     => $options['risk_rating'] ?? 'low',
                        'pep_status'      => $options['pep_status'] ?? false,
                    ]
                );

                // Mark all pending documents as verified
                $user->kycDocuments()
                    ->pending()
                    ->each(
                        function ($document) use ($verifiedBy, $options) {
                            $document->markAsVerified($verifiedBy, $options['document_expires_at'] ?? null);
                        }
                    );

                // Log the verification
                AuditLog::log(
                    'kyc.approved',
                    $user,
                    ['kyc_status'  => $oldStatus],
                    ['kyc_status'  => 'approved', 'kyc_level' => $user->kyc_level],
                    ['verified_by' => $verifiedBy, 'options' => $options],
                    'kyc,compliance,verification'
                );
            }
        );
    }

    /**
     * Reject user KYC.
     */
    public function rejectKyc(User $user, string $rejectedBy, string $reason): void
    {
        DB::transaction(
            function () use ($user, $rejectedBy, $reason) {
                $oldStatus = $user->kyc_status;

                // Update user status
                $user->update(
                    [
                        'kyc_status'      => 'rejected',
                        'kyc_rejected_at' => now(),
                    ]
                );

                // Mark documents as rejected
                $user->kycDocuments()
                    ->pending()
                    ->each(
                        function ($document) use ($reason, $rejectedBy) {
                            $document->markAsRejected($reason, $rejectedBy);
                        }
                    );

                // Log the rejection
                AuditLog::log(
                    'kyc.rejected',
                    $user,
                    ['kyc_status'  => $oldStatus],
                    ['kyc_status'  => 'rejected'],
                    ['rejected_by' => $rejectedBy, 'reason' => $reason],
                    'kyc,compliance,rejection'
                );
            }
        );
    }

    /**
     * Check if KYC is expired and update status.
     */
    public function checkExpiredKyc(User $user): bool
    {
        if ($user->kyc_status === 'approved' && $user->kyc_expires_at && $user->kyc_expires_at->isPast()) {
            $user->update(['kyc_status' => 'expired']);

            AuditLog::log(
                'kyc.expired',
                $user,
                ['kyc_status' => 'approved'],
                ['kyc_status' => 'expired'],
                null,
                'kyc,compliance,expired'
            );

            return true;
        }

        return false;
    }

    /**
     * Get KYC requirements for a specific level.
     */
    public function getRequirements(string $level): array
    {
        return match ($level) {
            'basic' => [
                'documents' => ['national_id', 'selfie'],
                'limits'    => [
                    'daily_transaction'   => 100000, // $1,000
                    'monthly_transaction' => 500000, // $5,000
                    'max_balance'         => 1000000, // $10,000
                ],
            ],
            'enhanced' => [
                'documents' => ['passport', 'utility_bill', 'selfie'],
                'limits'    => [
                    'daily_transaction'   => 1000000, // $10,000
                    'monthly_transaction' => 5000000, // $50,000
                    'max_balance'         => 10000000, // $100,000
                ],
            ],
            'full' => [
                'documents' => ['passport', 'utility_bill', 'bank_statement', 'selfie', 'proof_of_income'],
                'limits'    => [
                    'daily_transaction'   => null, // No limit
                    'monthly_transaction' => null, // No limit
                    'max_balance'         => null, // No limit
                ],
            ],
            default => throw new InvalidArgumentException("Unknown KYC level: {$level}"),
        };
    }

    /**
     * Get KYC status for a user.
     */
    public function getKycStatus(User $user): string
    {
        // Check if KYC is expired
        $this->checkExpiredKyc($user);

        return $user->kyc_status ?? 'not_submitted';
    }

    /**
     * Check if user's KYC is approved.
     */
    public function isKycApproved(User $user): bool
    {
        return $this->getKycStatus($user) === 'approved';
    }
}
