<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Services\SignatureService;
use Exception;
use Workflow\Activity;

class VerifySignatureActivity extends Activity
{
    public function __construct(
        private readonly SignatureService $signatureService
    ) {
    }

    public function execute(
        array $transactionData,
        string $signature,
        string $publicKey,
        string $algorithm = 'RS256'
    ): array {
        try {
            $isValid = $this->signatureService->verifySignature(
                transactionData: $transactionData,
                signature: $signature,
                publicKey: $publicKey,
                algorithm: $algorithm
            );

            $jsonData = json_encode($transactionData);
            $dataHash = $jsonData !== false ? hash('sha256', $jsonData) : '';

            return [
                'success'   => true,
                'is_valid'  => $isValid,
                'algorithm' => $algorithm,
                'timestamp' => now()->toIso8601String(),
                'data_hash' => $dataHash,
            ];
        } catch (Exception $e) {
            return [
                'success'  => false,
                'is_valid' => false,
                'error'    => $e->getMessage(),
            ];
        }
    }
}
