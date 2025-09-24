<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use Workflow\Activity;

class VerifyIdentityDocumentActivity extends Activity
{
    /**
     * Verify agent's identity document.
     */
    public function execute(?string $documentPath, string $agentId): array
    {
        // In production, this would call ID verification services
        // like Jumio, Onfido, or government ID verification APIs

        if (! $documentPath) {
            return [
                'status'     => 'failed',
                'reason'     => 'No document provided',
                'confidence' => 0,
            ];
        }

        // Simulate ID verification
        return [
            'status'           => 'passed',
            'confidence'       => 95,
            'documentType'     => 'passport',
            'documentNumber'   => 'XXXXXXXX',
            'issuingCountry'   => 'US',
            'expirationDate'   => now()->addYears(5)->toDateString(),
            'documentExpired'  => false,
            'documentTampered' => false,
            'verifiedAt'       => now()->toIso8601String(),
        ];
    }
}
