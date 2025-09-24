<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use Workflow\Activity;

class VerifyBusinessRegistrationActivity extends Activity
{
    /**
     * Verify business registration documents.
     */
    public function execute(
        string $documentPath,
        ?string $businessName,
        string $countryCode
    ): array {
        // In production, this would call business verification services
        // like government registries, Dun & Bradstreet, etc.

        // Simulate business verification
        $isHighRisk = in_array($businessName, ['Crypto Exchange', 'Money Services', 'Gambling']);

        return [
            'status'             => 'verified',
            'businessName'       => $businessName,
            'registrationNumber' => 'REG-' . strtoupper(substr(md5($businessName ?? ''), 0, 8)),
            'registrationValid'  => true,
            'taxCompliant'       => true,
            'yearsInBusiness'    => 5,
            'isHighRisk'         => $isHighRisk,
            'industryCode'       => 'FINTECH',
            'verifiedAt'         => now()->toIso8601String(),
        ];
    }
}
