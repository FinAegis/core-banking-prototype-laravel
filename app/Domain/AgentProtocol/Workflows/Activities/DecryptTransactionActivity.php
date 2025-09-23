<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Services\EncryptionService;
use Exception;
use Illuminate\Support\Facades\Log;
use Workflow\Activity;

class DecryptTransactionActivity extends Activity
{
    public function execute(
        string $encryptedData,
        string $keyId,
        string $cipher = 'AES-256-GCM',
        array $metadata = []
    ): array {
        try {
            $encryptionService = app(EncryptionService::class);

            // Decrypt the transaction data
            $decryptedData = $encryptionService->decryptData(
                $encryptedData,
                $keyId,
                $cipher,
                $metadata
            );

            Log::info('Transaction data decrypted successfully', [
                'key_id'       => $keyId,
                'cipher'       => $cipher,
                'decrypted_at' => now()->toIso8601String(),
            ]);

            return [
                'success'        => true,
                'decrypted_data' => $decryptedData,
                'key_id'         => $keyId,
                'cipher'         => $cipher,
                'decrypted_at'   => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('Transaction decryption failed', [
                'error'  => $e->getMessage(),
                'key_id' => $keyId,
                'cipher' => $cipher,
            ]);

            return [
                'success'   => false,
                'error'     => $e->getMessage(),
                'key_id'    => $keyId,
                'cipher'    => $cipher,
                'failed_at' => now()->toIso8601String(),
            ];
        }
    }
}
