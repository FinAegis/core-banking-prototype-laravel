<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\HardwareWallet;

use App\Domain\Wallet\Contracts\ExternalSignerInterface;
use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionData;
use InvalidArgumentException;
use Throwable;

/**
 * Ledger hardware wallet signer service.
 *
 * Implements the ExternalSignerInterface for Ledger Nano S/X devices.
 * Handles transaction preparation and signature validation for Ledger devices.
 *
 * Note: Actual device communication happens in the browser via WebUSB.
 * This service prepares data for signing and validates returned signatures.
 */
class LedgerSignerService implements ExternalSignerInterface
{
    private const SIGNER_TYPE = 'hardware_ledger';

    /**
     * BIP44 coin types for supported chains.
     *
     * @var array<string, int>
     */
    private const COIN_TYPES = [
        'ethereum' => 60,
        'bitcoin'  => 0,
        'polygon'  => 60,
        'bsc'      => 60,
    ];

    /**
     * Supported blockchain chains for this signer.
     *
     * @var array<string>
     */
    private const SUPPORTED_CHAINS = ['ethereum', 'bitcoin', 'polygon', 'bsc'];

    public function getType(): string
    {
        return self::SIGNER_TYPE;
    }

    /**
     * @return array<string>
     */
    public function getSupportedChains(): array
    {
        return self::SUPPORTED_CHAINS;
    }

    /**
     * Prepare transaction data for Ledger signing.
     *
     * @return array{raw_data: string, display_data: array<string, mixed>, encoding: string}
     */
    public function prepareForSigning(TransactionData $transaction): array
    {
        $chain = $transaction->chain;

        if ($this->isEvmChain($chain)) {
            return $this->prepareEvmTransaction($transaction);
        }

        if ($chain === 'bitcoin') {
            return $this->prepareBitcoinTransaction($transaction);
        }

        throw new InvalidArgumentException("Unsupported chain for Ledger: {$chain}");
    }

    public function constructSignedTransaction(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): SignedTransaction {
        $chain = $transaction->chain;

        if ($this->isEvmChain($chain)) {
            return $this->constructEvmSignedTransaction($transaction, $signature, $publicKey);
        }

        if ($chain === 'bitcoin') {
            return $this->constructBitcoinSignedTransaction($transaction, $signature, $publicKey);
        }

        throw new InvalidArgumentException("Unsupported chain: {$chain}");
    }

    public function validateSignature(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): bool {
        // Basic validation - reject empty values
        if (empty($signature) || empty($publicKey)) {
            return false;
        }

        try {
            if ($this->isEvmChain($transaction->chain)) {
                return $this->validateEvmSignature($transaction, $signature, $publicKey);
            }

            if ($transaction->chain === 'bitcoin') {
                return $this->validateBitcoinSignature($transaction, $signature, $publicKey);
            }
        } catch (Throwable) {
            // Invalid signature format
            return false;
        }

        return false;
    }

    public function getDerivationPath(string $chain, int $accountIndex = 0): string
    {
        $coinType = self::COIN_TYPES[$chain] ?? 60;

        return "m/44'/{$coinType}'/0'/0/{$accountIndex}";
    }

    public function supportsChain(string $chain): bool
    {
        return in_array($chain, self::SUPPORTED_CHAINS, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfirmationSteps(): array
    {
        return [
            'steps' => [
                'connect'  => 'Connect your Ledger device',
                'unlock'   => 'Enter your PIN on the device',
                'open_app' => 'Open the appropriate app (Ethereum/Bitcoin)',
                'review'   => 'Review transaction details on device screen',
                'confirm'  => 'Press both buttons to confirm',
            ],
            'estimated_time_seconds'         => 30,
            'requires_physical_confirmation' => true,
        ];
    }

    /**
     * Prepare an EVM-compatible transaction for Ledger signing.
     *
     * @return array{raw_data: string, display_data: array<string, mixed>, encoding: string}
     */
    private function prepareEvmTransaction(TransactionData $transaction): array
    {
        $chainId = $this->getChainId($transaction->chain);

        $txData = [
            'to'       => $transaction->to,
            'value'    => $this->toHex($transaction->value),
            'data'     => $transaction->data ?? '0x',
            'nonce'    => $transaction->nonce !== null ? $this->toHex((string) $transaction->nonce) : '0x0',
            'gasLimit' => $transaction->gasLimit !== null ? $this->toHex($transaction->gasLimit) : '0x5208',
            'chainId'  => $chainId,
        ];

        // Use EIP-1559 if available, otherwise legacy
        if ($transaction->maxFeePerGas !== null) {
            $txData['maxFeePerGas'] = $this->toHex($transaction->maxFeePerGas);
            $txData['maxPriorityFeePerGas'] = $this->toHex($transaction->maxPriorityFeePerGas ?? '0');
            $txData['type'] = '0x02'; // EIP-1559
        } else {
            $txData['gasPrice'] = $transaction->gasPrice !== null
                ? $this->toHex($transaction->gasPrice)
                : '0x3B9ACA00'; // 1 Gwei default
        }

        $rawData = '0x' . $this->rlpEncode($txData);

        return [
            'raw_data'     => $rawData,
            'display_data' => [
                'from'            => $transaction->from,
                'to'              => $transaction->to,
                'value'           => $transaction->value,
                'value_formatted' => $this->formatEvmValue($transaction->value),
                'chain'           => $transaction->chain,
                'chain_id'        => $chainId,
                'gas_limit'       => $transaction->gasLimit ?? '21000',
                'nonce'           => $transaction->nonce ?? 0,
            ],
            'encoding' => 'rlp',
        ];
    }

    /**
     * Prepare a Bitcoin transaction for Ledger signing.
     *
     * @return array{raw_data: string, display_data: array<string, mixed>, encoding: string}
     */
    private function prepareBitcoinTransaction(TransactionData $transaction): array
    {
        $txData = [
            'version' => 2,
            'inputs'  => [],
            'outputs' => [
                [
                    'address' => $transaction->to,
                    'value'   => $transaction->value,
                ],
            ],
            'locktime' => 0,
        ];

        $rawData = json_encode($txData) ?: '';

        return [
            'raw_data'     => bin2hex($rawData),
            'display_data' => [
                'from'            => $transaction->from,
                'to'              => $transaction->to,
                'value'           => $transaction->value,
                'value_formatted' => $this->formatBtcValue($transaction->value),
                'chain'           => 'bitcoin',
            ],
            'encoding' => 'psbt',
        ];
    }

    /**
     * Construct a signed EVM transaction from signature.
     */
    private function constructEvmSignedTransaction(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): SignedTransaction {
        // Parse v, r, s from signature
        $sig = $this->parseEvmSignature($signature);

        // Construct raw signed transaction
        $chainId = $this->getChainId($transaction->chain);

        $signedTxData = [
            'nonce'    => $transaction->nonce !== null ? $this->toHex((string) $transaction->nonce) : '0x0',
            'gasPrice' => $transaction->gasPrice !== null ? $this->toHex($transaction->gasPrice) : '0x3B9ACA00',
            'gasLimit' => $transaction->gasLimit !== null ? $this->toHex($transaction->gasLimit) : '0x5208',
            'to'       => $transaction->to,
            'value'    => $this->toHex($transaction->value),
            'data'     => $transaction->data ?? '0x',
            'v'        => $sig['v'],
            'r'        => $sig['r'],
            's'        => $sig['s'],
        ];

        $rawTransaction = '0x' . $this->rlpEncode($signedTxData);
        $hash = '0x' . hash('sha256', hex2bin(ltrim($rawTransaction, '0x')) ?: '');

        return new SignedTransaction(
            rawTransaction: $rawTransaction,
            hash: $hash,
            transactionData: $transaction
        );
    }

    /**
     * Construct a signed Bitcoin transaction.
     */
    private function constructBitcoinSignedTransaction(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): SignedTransaction {
        // Simplified Bitcoin transaction construction
        $rawTransaction = $signature; // In practice, would assemble full tx
        $hash = hash('sha256', hash('sha256', hex2bin($rawTransaction) ?: '', true));

        return new SignedTransaction(
            rawTransaction: $rawTransaction,
            hash: $hash,
            transactionData: $transaction
        );
    }

    /**
     * Validate an EVM signature.
     *
     * NOTE: This is a prototype implementation that performs basic format validation.
     * Production implementation should use proper ECDSA signature verification
     * with ecrecover to verify the signature matches the public key.
     */
    private function validateEvmSignature(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): bool {
        // Parse signature components
        $sig = $this->parseEvmSignature($signature);

        // Basic format validation
        // In production, use proper ecrecover to verify signature matches public key
        $rWithoutPrefix = str_starts_with($sig['r'], '0x') ? substr($sig['r'], 2) : $sig['r'];
        $sWithoutPrefix = str_starts_with($sig['s'], '0x') ? substr($sig['s'], 2) : $sig['s'];

        return strlen($rWithoutPrefix) === 64 && strlen($sWithoutPrefix) === 64;
    }

    /**
     * Validate a Bitcoin signature.
     */
    private function validateBitcoinSignature(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): bool {
        // Simplified validation
        return strlen($signature) > 0 && strlen($publicKey) > 0;
    }

    /**
     * Parse EVM signature into v, r, s components.
     *
     * @return array{v: string, r: string, s: string}
     */
    private function parseEvmSignature(string $signature): array
    {
        // Remove 0x prefix if present
        $sig = str_starts_with($signature, '0x') ? substr($signature, 2) : $signature;

        if (strlen($sig) !== 130) {
            throw new InvalidArgumentException('Invalid signature length');
        }

        return [
            'r' => '0x' . substr($sig, 0, 64),
            's' => '0x' . substr($sig, 64, 64),
            'v' => '0x' . substr($sig, 128, 2),
        ];
    }

    /**
     * Check if chain is EVM-compatible.
     */
    private function isEvmChain(string $chain): bool
    {
        return in_array($chain, ['ethereum', 'polygon', 'bsc'], true);
    }

    /**
     * Get chain ID for EVM chains.
     */
    private function getChainId(string $chain): int
    {
        $defaults = [
            'ethereum' => 1,
            'polygon'  => 137,
            'bsc'      => 56,
        ];

        if (! isset($defaults[$chain])) {
            return 1;
        }

        // Use config if Laravel is available, otherwise fall back to defaults
        try {
            if (function_exists('config') && function_exists('app') && app()->bound('config')) {
                return (int) config("blockchain.{$chain}.chain_id", $defaults[$chain]);
            }
        } catch (Throwable) {
            // Laravel not bootstrapped, use defaults
        }

        return $defaults[$chain];
    }

    /**
     * Convert value to hex string.
     */
    private function toHex(string $value): string
    {
        if (str_starts_with($value, '0x')) {
            return $value;
        }

        $intValue = (int) $value;

        return '0x' . dechex($intValue);
    }

    /**
     * RLP encode data for EVM transactions.
     *
     * @param  array<string|int, mixed>  $data
     */
    private function rlpEncode(array $data): string
    {
        // Simplified RLP encoding - in production use a proper library
        $encoded = '';
        foreach ($data as $item) {
            if (is_array($item)) {
                $encoded .= $this->rlpEncode($item);
            } else {
                $hex = ltrim((string) $item, '0x');
                $encoded .= $hex;
            }
        }

        return $encoded;
    }

    /**
     * Format EVM value for display (wei to ETH).
     */
    private function formatEvmValue(string $weiValue): string
    {
        $wei = (float) $weiValue;
        $eth = $wei / 1e18;

        return number_format($eth, 8) . ' ETH';
    }

    /**
     * Format BTC value for display (satoshi to BTC).
     */
    private function formatBtcValue(string $satoshiValue): string
    {
        $satoshi = (float) $satoshiValue;
        $btc = $satoshi / 1e8;

        return number_format($btc, 8) . ' BTC';
    }
}
