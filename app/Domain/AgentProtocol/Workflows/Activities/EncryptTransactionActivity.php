<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Services\EncryptionService;
use Exception;
use Workflow\Activity;

class EncryptTransactionActivity extends Activity
{
    public function __construct(
        private readonly EncryptionService $encryptionService
    ) {
    }

    public function execute(
        array $transactionData,
        string $securityId
    ): array {
        try {
            $keyId = $this->generateKeyId($securityId);
            $cipher = 'AES-256-GCM';

            // Encrypt the transaction data
            $encryptedResult = $this->encryptionService->encryptData(
                data: $transactionData,
                keyId: $keyId,
                cipher: $cipher
            );

            return array_merge($encryptedResult, [
                'success' => true,
                'key_id'  => $keyId,
                'cipher'  => $cipher,
            ]);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    private function generateKeyId(string $securityId): string
    {
        return "key_{$securityId}";
    }
}
