<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Seeders;

use App\Domain\TrustCert\Enums\CertificateStatus;
use App\Domain\TrustCert\Enums\IssuerType;
use App\Domain\TrustCert\Models\Certificate;
use App\Models\User;

/**
 * Seeds exactly one active Certificate for a review/demo account.
 *
 * Review-account seeding shortcut — bypasses the full issuance/approval
 * workflow. The certificate is tagged with `metadata.source='review_bypass'`
 * so auditors can trace origin.
 *
 * Idempotent via firstOrCreate on (user_id, credential_type='review_bypass').
 */
class TrustCertSeeder
{
    public function seed(User $user): void
    {
        Certificate::firstOrCreate(
            [
                'user_id'         => $user->id,
                'credential_type' => 'review_bypass',
            ],
            [
                'subject'     => 'review:' . $user->uuid,
                'issuer_type' => IssuerType::TRUSTED_ISSUER,
                'status'      => CertificateStatus::ACTIVE,
                'claims'      => [
                    'review_account' => true,
                    'kyc_level'      => 'enhanced',
                ],
                'issued_at' => now(),
                'metadata'  => [
                    'source' => 'review_bypass',
                ],
            ]
        );
    }
}
