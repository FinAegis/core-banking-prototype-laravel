<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Models\Agent;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DigitalSignatureService
{
    public function __construct(
        private readonly SignatureService $signatureService,
        private readonly EncryptionService $encryptionService
    ) {
    }

    /**
     * Sign an agent transaction with enhanced security features.
     */
    public function signAgentTransaction(
        string $transactionId,
        string $agentId,
        array $transactionData,
        array $options = []
    ): array {
        try {
            // Get agent's private key
            $privateKey = $this->getAgentPrivateKey($agentId);

            // Add transaction metadata for non-repudiation
            $dataToSign = $this->prepareTransactionData($transactionId, $agentId, $transactionData);

            // Determine signature algorithm based on security level
            $algorithm = $options['algorithm'] ?? $this->selectAlgorithm($options['security_level'] ?? 'standard');

            // Create signature with timestamp
            $signature = $this->signatureService->signTransaction(
                $dataToSign,
                $privateKey,
                $algorithm
            );

            // Add additional security metadata
            $signature['transaction_id'] = $transactionId;
            $signature['agent_id'] = $agentId;
            $signature['nonce'] = $this->generateNonce();
            $signature['expires_at'] = now()->addMinutes($options['ttl'] ?? 60)->toIso8601String();

            // Store signature for audit trail
            $this->storeSignature($transactionId, $agentId, $signature);

            // Log successful signing
            Log::info('Agent transaction signed', [
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'algorithm'      => $algorithm,
            ]);

            return $signature;
        } catch (Exception $e) {
            Log::error('Agent transaction signing failed', [
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'error'          => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Verify an agent transaction signature with enhanced checks.
     */
    public function verifyAgentSignature(
        string $transactionId,
        string $agentId,
        array $transactionData,
        string $signature,
        array $metadata
    ): array {
        try {
            // Check signature expiration
            if ($this->isSignatureExpired($metadata)) {
                return [
                    'is_valid'       => false,
                    'reason'         => 'Signature has expired',
                    'transaction_id' => $transactionId,
                ];
            }

            // Get agent's public key
            $publicKey = $this->getAgentPublicKey($agentId);

            // Prepare data for verification
            $dataToVerify = $this->prepareTransactionData($transactionId, $agentId, $transactionData);

            // Verify signature
            $isValid = $this->signatureService->verifySignature(
                $dataToVerify,
                $signature,
                $publicKey,
                $metadata['algorithm'] ?? 'RS256'
            );

            // Check nonce for replay protection
            if ($isValid && ! $this->verifyNonce($metadata['nonce'] ?? '')) {
                return [
                    'is_valid'       => false,
                    'reason'         => 'Invalid or reused nonce',
                    'transaction_id' => $transactionId,
                ];
            }

            // Log verification result
            Log::info('Agent signature verification', [
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'is_valid'       => $isValid,
            ]);

            return [
                'is_valid'       => $isValid,
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'verified_at'    => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('Agent signature verification failed', [
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'error'          => $e->getMessage(),
            ]);

            return [
                'is_valid'       => false,
                'reason'         => 'Verification error: ' . $e->getMessage(),
                'transaction_id' => $transactionId,
            ];
        }
    }

    /**
     * Create a multi-party signature for agent collaborations.
     */
    public function createMultiPartySignature(
        string $transactionId,
        array $participatingAgents,
        array $transactionData,
        int $requiredSignatures
    ): array {
        try {
            $signatures = [];

            foreach ($participatingAgents as $agentId => $privateKey) {
                $dataToSign = $this->prepareTransactionData($transactionId, $agentId, $transactionData);

                $signature = $this->signatureService->signTransaction(
                    $dataToSign,
                    $privateKey,
                    'RS256'
                );

                $signatures[$agentId] = [
                    'signature' => $signature['signature'],
                    'algorithm' => $signature['algorithm'],
                    'timestamp' => $signature['timestamp'],
                    'agent_id'  => $agentId,
                ];
            }

            $multiSig = $this->signatureService->createMultiSignature(
                $transactionData,
                $signatures,
                $requiredSignatures
            );

            // Store multi-party signature
            $this->storeMultiPartySignature($transactionId, $multiSig);

            Log::info('Multi-party signature created', [
                'transaction_id' => $transactionId,
                'participants'   => count($participatingAgents),
                'required'       => $requiredSignatures,
                'threshold_met'  => $multiSig['threshold_met'],
            ]);

            return $multiSig;
        } catch (Exception $e) {
            Log::error('Multi-party signature creation failed', [
                'transaction_id' => $transactionId,
                'error'          => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate and store agent key pair.
     */
    public function generateAgentKeyPair(string $agentId, string $type = 'RSA'): array
    {
        try {
            $keyPair = $this->signatureService->generateKeyPair($type, 4096);

            // Encrypt private key before storage
            $encryptedPrivateKey = $this->encryptionService->encryptData(
                ['private_key' => $keyPair['private_key']],
                "agent_key_{$agentId}"
            );

            // Store keys securely
            $this->storeAgentKeys($agentId, $encryptedPrivateKey, $keyPair['public_key']);

            Log::info('Agent key pair generated', [
                'agent_id' => $agentId,
                'type'     => $type,
                'key_size' => $keyPair['size'] ?? 'default',
            ]);

            return [
                'agent_id'     => $agentId,
                'public_key'   => $keyPair['public_key'],
                'type'         => $type,
                'generated_at' => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('Agent key pair generation failed', [
                'agent_id' => $agentId,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Rotate agent signing keys.
     */
    public function rotateAgentKeys(string $agentId): array
    {
        try {
            // Generate new key pair
            $newKeyPair = $this->generateAgentKeyPair($agentId);

            // Archive old keys
            $this->archiveAgentKeys($agentId);

            // Update agent's DID document with new public key
            $this->updateAgentDIDDocument($agentId, $newKeyPair['public_key']);

            Log::info('Agent keys rotated', [
                'agent_id'   => $agentId,
                'rotated_at' => now()->toIso8601String(),
            ]);

            return [
                'agent_id'       => $agentId,
                'new_public_key' => $newKeyPair['public_key'],
                'rotated_at'     => now()->toIso8601String(),
                'next_rotation'  => now()->addDays(90)->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('Agent key rotation failed', [
                'agent_id' => $agentId,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a signature proof for zero-knowledge verification.
     */
    public function createSignatureProof(
        string $transactionId,
        string $agentId,
        array $commitments
    ): array {
        try {
            // Generate challenge
            $challenge = $this->generateChallenge($transactionId, $agentId);

            // Create proof without revealing private key
            $proof = [
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'challenge'      => $challenge,
                'commitments'    => $commitments,
                'proof_hash'     => hash('sha256', json_encode($commitments) . $challenge),
                'timestamp'      => now()->toIso8601String(),
            ];

            // Store proof for verification
            $this->storeSignatureProof($transactionId, $proof);

            return $proof;
        } catch (Exception $e) {
            Log::error('Signature proof creation failed', [
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'error'          => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Prepare transaction data with metadata for signing.
     */
    private function prepareTransactionData(
        string $transactionId,
        string $agentId,
        array $transactionData
    ): array {
        return [
            'transaction_id' => $transactionId,
            'agent_id'       => $agentId,
            'data'           => $transactionData,
            'timestamp'      => now()->toIso8601String(),
            'version'        => '1.0',
        ];
    }

    /**
     * Select signature algorithm based on security level.
     */
    private function selectAlgorithm(string $securityLevel): string
    {
        return match ($securityLevel) {
            'maximum'  => 'RS512',
            'enhanced' => 'RS384',
            'standard' => 'RS256',
            default    => 'RS256',
        };
    }

    /**
     * Generate a cryptographically secure nonce.
     */
    private function generateNonce(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Verify nonce hasn't been used before (replay protection).
     */
    private function verifyNonce(string $nonce): bool
    {
        if (empty($nonce)) {
            return false;
        }

        $key = "nonce:{$nonce}";

        // Check if nonce exists (already used)
        if (Cache::has($key)) {
            return false;
        }

        // Mark nonce as used (store for 24 hours)
        Cache::put($key, true, 86400);

        return true;
    }

    /**
     * Check if signature has expired.
     */
    private function isSignatureExpired(array $metadata): bool
    {
        if (! isset($metadata['expires_at'])) {
            return false;
        }

        return now()->isAfter($metadata['expires_at']);
    }

    /**
     * Get agent's private key (decrypted).
     */
    private function getAgentPrivateKey(string $agentId): string
    {
        $encryptedKey = Cache::get("agent_private_key:{$agentId}");

        if (! $encryptedKey) {
            throw new Exception("Private key not found for agent: {$agentId}");
        }

        $decrypted = $this->encryptionService->decryptData(
            $encryptedKey['encrypted_data'],
            "agent_key_{$agentId}",
            $encryptedKey['cipher'],
            $encryptedKey
        );

        return $decrypted['private_key'];
    }

    /**
     * Get agent's public key.
     */
    private function getAgentPublicKey(string $agentId): string
    {
        $publicKey = Cache::get("agent_public_key:{$agentId}");

        if (! $publicKey) {
            // Try to fetch from agent model
            $agent = Agent::where('agent_id', $agentId)->first();
            if ($agent && $agent->public_key) {
                return $agent->public_key;
            }

            throw new Exception("Public key not found for agent: {$agentId}");
        }

        return $publicKey;
    }

    /**
     * Store agent keys securely.
     */
    private function storeAgentKeys(string $agentId, array $encryptedPrivateKey, string $publicKey): void
    {
        // Store encrypted private key
        Cache::put("agent_private_key:{$agentId}", $encryptedPrivateKey, now()->addDays(90));

        // Store public key
        Cache::put("agent_public_key:{$agentId}", $publicKey, now()->addDays(90));

        // Also update agent model
        Agent::where('agent_id', $agentId)->update([
            'public_key'     => $publicKey,
            'key_rotated_at' => now(),
        ]);
    }

    /**
     * Archive old agent keys.
     */
    private function archiveAgentKeys(string $agentId): void
    {
        $privateKey = Cache::get("agent_private_key:{$agentId}");
        $publicKey = Cache::get("agent_public_key:{$agentId}");

        if ($privateKey) {
            Cache::put("archived_private_key:{$agentId}:" . time(), $privateKey, now()->addYears(1));
        }

        if ($publicKey) {
            Cache::put("archived_public_key:{$agentId}:" . time(), $publicKey, now()->addYears(1));
        }
    }

    /**
     * Update agent's DID document with new public key.
     */
    private function updateAgentDIDDocument(string $agentId, string $publicKey): void
    {
        // This would integrate with the DID system
        // For now, just log the update
        Log::info('Agent DID document updated with new public key', [
            'agent_id' => $agentId,
        ]);
    }

    /**
     * Store signature for audit trail.
     */
    private function storeSignature(string $transactionId, string $agentId, array $signature): void
    {
        $key = "signature:{$transactionId}:{$agentId}";
        Cache::put($key, $signature, now()->addDays(30));
    }

    /**
     * Store multi-party signature.
     */
    private function storeMultiPartySignature(string $transactionId, array $multiSig): void
    {
        $key = "multi_signature:{$transactionId}";
        Cache::put($key, $multiSig, now()->addDays(30));
    }

    /**
     * Generate challenge for zero-knowledge proof.
     */
    private function generateChallenge(string $transactionId, string $agentId): string
    {
        return hash('sha256', $transactionId . $agentId . microtime(true));
    }

    /**
     * Store signature proof.
     */
    private function storeSignatureProof(string $transactionId, array $proof): void
    {
        $key = "signature_proof:{$transactionId}";
        Cache::put($key, $proof, now()->addDays(7));
    }
}
