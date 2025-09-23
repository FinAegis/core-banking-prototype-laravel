<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Services\SignatureService;
use Exception;
use Workflow\Activity;

class SignTransactionActivity extends Activity
{
    public function __construct(
        private readonly SignatureService $signatureService
    ) {
    }

    public function execute(
        string $transactionId,
        array $transactionData,
        string $agentId
    ): array {
        try {
            // Get or generate key pair for agent
            $keyPair = $this->getAgentKeyPair($agentId);

            // Sign the transaction
            $signature = $this->signatureService->signTransaction(
                transactionData: array_merge($transactionData, ['transaction_id' => $transactionId]),
                privateKey: $keyPair['private_key'],
                algorithm: 'RS256'
            );

            return [
                'success'    => true,
                'signature'  => $signature['signature'],
                'algorithm'  => $signature['algorithm'],
                'public_key' => $keyPair['public_key'],
                'timestamp'  => $signature['timestamp'],
                'data_hash'  => $signature['data_hash'],
                'agent_id'   => $agentId,
            ];
        } catch (Exception $e) {
            return [
                'success'  => false,
                'error'    => $e->getMessage(),
                'agent_id' => $agentId,
            ];
        }
    }

    private function getAgentKeyPair(string $agentId): array
    {
        // In production, retrieve from secure storage
        // For now, generate a new key pair
        return $this->signatureService->generateKeyPair('RSA', 2048);
    }
}
