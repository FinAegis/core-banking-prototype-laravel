<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\HardwareWallet;

use App\Domain\Wallet\Contracts\ExternalSignerInterface;
use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionData;
use RuntimeException;

/**
 * Mock Hardware Wallet Service for Testing.
 *
 * Provides a mock implementation of the ExternalSignerInterface
 * for use in automated testing without requiring physical hardware.
 */
class MockHardwareWalletService implements ExternalSignerInterface
{
    /**
     * Mock device type.
     */
    private string $deviceType = 'mock_hardware';

    /**
     * Flag to simulate failures.
     */
    private bool $shouldFail = false;

    /**
     * Delay to simulate signing time (milliseconds).
     */
    private int $signingDelayMs = 0;

    /**
     * History of signing operations.
     *
     * @var array<int, array{transaction: TransactionData, signature: string, timestamp: int}>
     */
    private array $signingHistory = [];

    /**
     * Configure the mock to simulate a specific device type.
     */
    public function setDeviceType(string $type): self
    {
        $this->deviceType = $type;

        return $this;
    }

    /**
     * Configure the mock to fail on signing operations.
     */
    public function setShouldFail(bool $shouldFail): self
    {
        $this->shouldFail = $shouldFail;

        return $this;
    }

    /**
     * Configure signing delay to simulate real device interaction.
     */
    public function setSigningDelay(int $milliseconds): self
    {
        $this->signingDelayMs = $milliseconds;

        return $this;
    }

    /**
     * Set a predefined signature for a specific transaction hash.
     */
    public function setPredefinedSignature(string $transactionId, string $signature): self
    {
        $this->predefinedSignatures[$transactionId] = $signature;

        return $this;
    }

    /**
     * Get the signing history for assertions in tests.
     *
     * @return array<int, array{transaction: TransactionData, signature: string, timestamp: int}>
     */
    public function getSigningHistory(): array
    {
        return $this->signingHistory;
    }

    /**
     * Clear the signing history.
     */
    public function clearHistory(): self
    {
        $this->signingHistory = [];

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return $this->deviceType;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public function getSupportedChains(): array
    {
        return ['ethereum', 'polygon', 'bsc', 'bitcoin'];
    }

    /**
     * {@inheritDoc}
     */
    public function supportsChain(string $chain): bool
    {
        return in_array($chain, $this->getSupportedChains(), true);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function prepareForSigning(TransactionData $transaction): array
    {
        if ($this->shouldFail) {
            throw new RuntimeException('Mock device connection failed');
        }

        $chainId = $this->getChainId($transaction->chain);
        $rawTransaction = $this->createMockRawTransaction($transaction);

        return [
            'raw_transaction' => $rawTransaction,
            'signing_format'  => 'mock_format',
            'derivation_path' => $this->getDerivationPath($transaction->chain),
            'chain_id'        => $chainId,
            'display_amount'  => $transaction->value,
            'display_to'      => $transaction->to,
            'estimated_gas'   => $transaction->gasLimit ?? '21000',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function constructSignedTransaction(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): SignedTransaction {
        if ($this->shouldFail) {
            throw new RuntimeException('Mock signing failed');
        }

        // Simulate signing delay
        if ($this->signingDelayMs > 0) {
            usleep($this->signingDelayMs * 1000);
        }

        // Generate mock signed transaction
        $signedRaw = '0x' . bin2hex(random_bytes(100));
        $txHash = '0x' . bin2hex(random_bytes(32));

        // Record in history
        $this->signingHistory[] = [
            'transaction' => $transaction,
            'signature'   => $signature,
            'timestamp'   => time(),
        ];

        return new SignedTransaction(
            rawTransaction: $signedRaw,
            hash: $txHash,
            transactionData: $transaction
        );
    }

    /**
     * {@inheritDoc}
     */
    public function validateSignature(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): bool {
        if ($this->shouldFail) {
            return false;
        }

        // Basic validation
        if (empty($signature) || empty($publicKey)) {
            return false;
        }

        // Mock always returns true for non-empty values
        return strlen($signature) >= 64 && strlen($publicKey) >= 64;
    }

    /**
     * {@inheritDoc}
     */
    public function getDerivationPath(string $chain, int $accountIndex = 0): string
    {
        $coinType = match ($chain) {
            'bitcoin' => 0,
            default   => 60, // Ethereum
        };

        return "m/44'/{$coinType}'/0'/0/{$accountIndex}";
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public function getConfirmationSteps(): array
    {
        return [
            'This is a mock device - no actual hardware required',
            'Signing will be simulated automatically',
            'Use for testing purposes only',
        ];
    }

    /**
     * Get chain ID for a given chain.
     */
    private function getChainId(string $chain): int
    {
        return match ($chain) {
            'ethereum' => 1,
            'polygon'  => 137,
            'bsc'      => 56,
            default    => 0,
        };
    }

    /**
     * Create a mock raw transaction for signing.
     */
    private function createMockRawTransaction(TransactionData $transaction): string
    {
        $data = [
            'to'       => $transaction->to,
            'value'    => $transaction->value,
            'gasPrice' => $transaction->gasPrice ?? '0',
            'gasLimit' => $transaction->gasLimit ?? '21000',
            'nonce'    => $transaction->nonce ?? 0,
            'data'     => $transaction->data ?? '',
            'chainId'  => $this->getChainId($transaction->chain),
        ];

        return '0x' . bin2hex(json_encode($data) ?: '{}');
    }

    /**
     * Generate a mock signature for testing.
     */
    public function generateMockSignature(): string
    {
        $r = str_repeat('ab', 32);
        $s = str_repeat('cd', 32);
        $v = '1b';

        return '0x' . $r . $s . $v;
    }

    /**
     * Generate a mock public key for testing.
     */
    public function generateMockPublicKey(): string
    {
        return '04' . str_repeat('ef', 64);
    }

    /**
     * Simulate a complete signing flow for testing.
     *
     * @return array{signature: string, public_key: string, signed_transaction: SignedTransaction}
     */
    public function simulateCompleteSigning(TransactionData $transaction): array
    {
        $signature = $this->generateMockSignature();
        $publicKey = $this->generateMockPublicKey();
        $signedTx = $this->constructSignedTransaction($transaction, $signature, $publicKey);

        return [
            'signature'          => $signature,
            'public_key'         => $publicKey,
            'signed_transaction' => $signedTx,
        ];
    }
}
