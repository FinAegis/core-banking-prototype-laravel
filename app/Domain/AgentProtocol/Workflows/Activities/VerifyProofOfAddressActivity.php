<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use Workflow\Activity;

class VerifyProofOfAddressActivity extends Activity
{
    /**
     * Verify proof of address document.
     */
    public function execute(string $documentPath, string $agentId): array
    {
        // In production, this would call address verification services
        // like Trulioo, Experian, or utility company APIs

        // Simulate address verification
        return [
            'status'       => 'passed',
            'addressMatch' => true,
            'documentType' => 'utility_bill',
            'documentAge'  => 45, // days old
            'address'      => [
                'line1'      => '123 Main Street',
                'line2'      => 'Suite 100',
                'city'       => 'New York',
                'state'      => 'NY',
                'postalCode' => '10001',
                'country'    => 'US',
            ],
            'confidence' => 92,
            'verifiedAt' => now()->toIso8601String(),
        ];
    }
}
