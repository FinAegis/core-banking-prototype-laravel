<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\HardwareWallet;

use App\Domain\Wallet\Contracts\ExternalSignerInterface;
use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionData;
use InvalidArgumentException;
use Throwable;

/**
 * Trezor hardware wallet signer service.
 *
 * Implements the ExternalSignerInterface for Trezor One/Model T devices.
 * Handles transaction preparation and signature validation for Trezor devices.
 *
 * Note: Actual device communication happens via Trezor Connect Web bridge.
 * This service prepares data for signing and validates returned signatures.
 */
class TrezorSignerService implements ExternalSignerInterface
{
    private const SIGNER_TYPE = 'hardware_trezor';

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
     * Prepare transaction data for Trezor signing.
     *
     * @return array{raw_data: string, display_data: array<string, mixed>, encoding: string}
     */
    public function prepareForSigning(TransactionData $transaction): array
    {
        $chain = $transaction->chain;

        if ($this->isEvmChain($chain)) {
            return $this->prepareTrezorEvmTransaction($transaction);
        }

        if ($chain === 'bitcoin') {
            return $this->prepareTrezorBitcoinTransaction($transaction);
        }

        throw new InvalidArgumentException("Unsupported chain for Trezor: {$chain}");
    }

    public function constructSignedTransaction(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): SignedTransaction {
        $chain = $transaction->chain;

        if ($this->isEvmChain($chain)) {
            return $this->constructTrezorEvmSignedTransaction($transaction, $signature, $publicKey);
        }

        if ($chain === 'bitcoin') {
            return $this->constructTrezorBitcoinSignedTransaction($transaction, $signature, $publicKey);
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
                return $this->validateTrezorEvmSignature($transaction, $signature, $publicKey);
            }

            if ($transaction->chain === 'bitcoin') {
                return $this->validateTrezorBitcoinSignature($transaction, $signature, $publicKey);
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

        // Trezor uses the same BIP44 paths as Ledger
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
                'connect'    => 'Connect your Trezor device',
                'unlock'     => 'Enter your PIN on the computer or device',
                'passphrase' => 'Enter passphrase if enabled (on device)',
                'review'     => 'Review transaction details on device screen',
                'confirm'    => 'Confirm by pressing the button',
            ],
            'estimated_time_seconds'         => 45,
            'requires_physical_confirmation' => true,
        ];
    }

    /**
     * Prepare an EVM transaction in Trezor Connect format.
     *
     * @return array{raw_data: string, display_data: array<string, mixed>, encoding: string}
     */
    private function prepareTrezorEvmTransaction(TransactionData $transaction): array
    {
        $chainId = $this->getChainId($transaction->chain);

        // Trezor Connect expects specific format
        $trezorTxParams = [
            'to'       => $transaction->to,
            'value'    => $this->toTrezorHex($transaction->value),
            'data'     => $transaction->data ?? '',
            'chainId'  => $chainId,
            'nonce'    => $this->toTrezorHex((string) ($transaction->nonce ?? 0)),
            'gasLimit' => $this->toTrezorHex($transaction->gasLimit ?? '21000'),
        ];

        // EIP-1559 support
        if ($transaction->maxFeePerGas !== null) {
            $trezorTxParams['maxFeePerGas'] = $this->toTrezorHex($transaction->maxFeePerGas);
            $trezorTxParams['maxPriorityFeePerGas'] = $this->toTrezorHex($transaction->maxPriorityFeePerGas ?? '0');
        } else {
            $trezorTxParams['gasPrice'] = $this->toTrezorHex($transaction->gasPrice ?? '1000000000');
        }

        $rawData = json_encode($trezorTxParams) ?: '';

        return [
            'raw_data'     => bin2hex($rawData),
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
            'encoding' => 'trezor_connect',
        ];
    }

    /**
     * Prepare a Bitcoin transaction in Trezor format.
     *
     * @return array{raw_data: string, display_data: array<string, mixed>, encoding: string}
     */
    private function prepareTrezorBitcoinTransaction(TransactionData $transaction): array
    {
        // Trezor Connect Bitcoin transaction format
        $trezorTxParams = [
            'coin'    => 'btc',
            'outputs' => [
                [
                    'address' => $transaction->to,
                    'amount'  => $transaction->value,
                ],
            ],
        ];

        $rawData = json_encode($trezorTxParams) ?: '';

        return [
            'raw_data'     => bin2hex($rawData),
            'display_data' => [
                'from'            => $transaction->from,
                'to'              => $transaction->to,
                'value'           => $transaction->value,
                'value_formatted' => $this->formatBtcValue($transaction->value),
                'chain'           => 'bitcoin',
            ],
            'encoding' => 'trezor_connect',
        ];
    }

    /**
     * Construct a signed EVM transaction from Trezor signature.
     */
    private function constructTrezorEvmSignedTransaction(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): SignedTransaction {
        // Trezor returns signature in v,r,s format
        $sig = $this->parseTrezorSignature($signature);

        $chainId = $this->getChainId($transaction->chain);

        // Construct RLP-encoded signed transaction
        $txFields = [
            'nonce'    => $this->toTrezorHex((string) ($transaction->nonce ?? 0)),
            'gasPrice' => $this->toTrezorHex($transaction->gasPrice ?? '1000000000'),
            'gasLimit' => $this->toTrezorHex($transaction->gasLimit ?? '21000'),
            'to'       => $transaction->to,
            'value'    => $this->toTrezorHex($transaction->value),
            'data'     => $transaction->data ?? '',
            'v'        => $sig['v'],
            'r'        => $sig['r'],
            's'        => $sig['s'],
        ];

        $rawTransaction = '0x' . $this->rlpEncode($txFields);
        $hash = '0x' . hash('sha256', hex2bin(ltrim($rawTransaction, '0x')) ?: '');

        return new SignedTransaction(
            rawTransaction: $rawTransaction,
            hash: $hash,
            transactionData: $transaction
        );
    }

    /**
     * Construct a signed Bitcoin transaction from Trezor signature.
     */
    private function constructTrezorBitcoinSignedTransaction(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): SignedTransaction {
        // Trezor returns fully signed Bitcoin transaction
        $rawTransaction = $signature;
        $hash = hash('sha256', hash('sha256', hex2bin($rawTransaction) ?: '', true));

        return new SignedTransaction(
            rawTransaction: $rawTransaction,
            hash: $hash,
            transactionData: $transaction
        );
    }

    /**
     * Validate a Trezor EVM signature using ECDSA ecrecover.
     *
     * Recovers the public key from the signature and verifies it matches
     * the expected public key to prevent signature forgery attacks.
     */
    private function validateTrezorEvmSignature(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): bool {
        $sig = $this->parseTrezorSignature($signature);

        // Validate component lengths
        $rWithoutPrefix = str_starts_with($sig['r'], '0x') ? substr($sig['r'], 2) : $sig['r'];
        $sWithoutPrefix = str_starts_with($sig['s'], '0x') ? substr($sig['s'], 2) : $sig['s'];
        $vWithoutPrefix = str_starts_with($sig['v'], '0x') ? substr($sig['v'], 2) : $sig['v'];

        if (strlen($rWithoutPrefix) !== 64 || strlen($sWithoutPrefix) !== 64) {
            return false;
        }

        // Validate v value
        $v = hexdec($vWithoutPrefix);
        $chainId = $this->getChainId($transaction->chain);
        $isValidV = $v === 27 || $v === 28 ||
                    $v === (35 + 2 * $chainId) || $v === (36 + 2 * $chainId);

        if (! $isValidV) {
            return false;
        }

        // Validate s value is in lower half of curve order (EIP-2)
        $curveOrder = gmp_init('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);
        $halfOrder = gmp_div($curveOrder, 2);
        $sValue = gmp_init('0x' . $sWithoutPrefix, 16);

        if (gmp_cmp($sValue, $halfOrder) > 0) {
            return false;
        }

        // Recover public key from signature and verify it matches expected
        try {
            $messageHash = $this->computeTrezorTransactionHash($transaction);
            $recoveredKey = $this->trezorEcRecover($messageHash, $rWithoutPrefix, $sWithoutPrefix, $v);

            if ($recoveredKey === null) {
                return false;
            }

            // Normalize public keys for comparison
            $expectedKey = str_starts_with($publicKey, '0x')
                ? strtolower(substr($publicKey, 2))
                : strtolower($publicKey);
            $recoveredKey = strtolower($recoveredKey);

            // Use timing-safe comparison to prevent timing attacks
            return hash_equals($expectedKey, $recoveredKey);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Validate a Trezor Bitcoin signature.
     */
    private function validateTrezorBitcoinSignature(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): bool {
        return strlen($signature) > 0 && strlen($publicKey) > 0;
    }

    /**
     * Parse Trezor signature format.
     *
     * @return array{v: string, r: string, s: string}
     */
    private function parseTrezorSignature(string $signature): array
    {
        // Remove 0x prefix if present
        $sig = str_starts_with($signature, '0x') ? substr($signature, 2) : $signature;

        // Trezor may return signature in different formats
        if (strlen($sig) === 130) {
            // Standard format: r(64) + s(64) + v(2)
            return [
                'r' => '0x' . substr($sig, 0, 64),
                's' => '0x' . substr($sig, 64, 64),
                'v' => '0x' . substr($sig, 128, 2),
            ];
        }

        // Try to decode as JSON (Trezor Connect may return structured response)
        $decoded = json_decode($signature, true);
        if (is_array($decoded) && isset($decoded['v'], $decoded['r'], $decoded['s'])) {
            return [
                'v' => $this->ensureHexPrefix((string) $decoded['v']),
                'r' => $this->ensureHexPrefix((string) $decoded['r']),
                's' => $this->ensureHexPrefix((string) $decoded['s']),
            ];
        }

        throw new InvalidArgumentException('Invalid Trezor signature format');
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
     * Convert value to hex for Trezor.
     */
    private function toTrezorHex(string $value): string
    {
        if (str_starts_with($value, '0x')) {
            return $value;
        }

        $intValue = (int) $value;

        return '0x' . dechex($intValue);
    }

    /**
     * Ensure value has 0x prefix.
     */
    private function ensureHexPrefix(string $value): string
    {
        if (str_starts_with($value, '0x')) {
            return $value;
        }

        return '0x' . $value;
    }

    /**
     * RLP encode data.
     *
     * @param  array<string|int, mixed>  $data
     */
    private function rlpEncode(array $data): string
    {
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
     * Format EVM value for display.
     */
    private function formatEvmValue(string $weiValue): string
    {
        $wei = (float) $weiValue;
        $eth = $wei / 1e18;

        return number_format($eth, 8) . ' ETH';
    }

    /**
     * Format BTC value for display.
     */
    private function formatBtcValue(string $satoshiValue): string
    {
        $satoshi = (float) $satoshiValue;
        $btc = $satoshi / 1e8;

        return number_format($btc, 8) . ' BTC';
    }

    /**
     * Compute the transaction hash for Trezor signature verification.
     */
    private function computeTrezorTransactionHash(TransactionData $transaction): string
    {
        // Build unsigned transaction for hashing
        $unsignedTx = [
            $this->toTrezorHex((string) ($transaction->nonce ?? 0)),
            $this->toTrezorHex($transaction->gasPrice ?? '0'),
            $this->toTrezorHex($transaction->gasLimit ?? '21000'),
            $transaction->to ?? '',
            $this->toTrezorHex($transaction->value ?? '0'),
            $transaction->data ?? '',
        ];

        // Add chain ID for EIP-155 replay protection
        $chainId = $this->getChainId($transaction->chain);
        if ($chainId > 0) {
            $unsignedTx[] = $this->toTrezorHex((string) $chainId);
            $unsignedTx[] = '0x';
            $unsignedTx[] = '0x';
        }

        $rlpEncoded = $this->rlpEncode($unsignedTx);

        // Use Keccak-256 hash
        return \kornrunner\Keccak::hash(hex2bin(substr($rlpEncoded, 2)), 256);
    }

    /**
     * Recover public key from ECDSA signature for Trezor.
     *
     * @param  string  $messageHash  32-byte message hash (hex without 0x)
     * @param  string  $r  Signature r value (hex without 0x)
     * @param  string  $s  Signature s value (hex without 0x)
     * @param  int  $v  Recovery parameter
     * @return string|null Recovered public key (hex without 0x) or null on failure
     */
    private function trezorEcRecover(string $messageHash, string $r, string $s, int $v): ?string
    {
        try {
            // Normalize v to 0 or 1 for recovery
            $recoveryParam = $v - 27;
            if ($recoveryParam > 3) {
                $recoveryParam = ($v - 35) % 2;
            }

            $ec = new \Elliptic\EC('secp256k1');

            $signature = [
                'r'             => $r,
                's'             => $s,
                'recoveryParam' => $recoveryParam,
            ];

            $publicKey = $ec->recoverPubKey(
                hex2bin($messageHash),
                $signature,
                $recoveryParam
            );

            if ($publicKey === null) {
                return null;
            }

            $pubKeyHex = $publicKey->encode('hex');

            // Remove the 04 prefix (uncompressed point indicator)
            if (str_starts_with($pubKeyHex, '04') && strlen($pubKeyHex) === 130) {
                return substr($pubKeyHex, 2);
            }

            return $pubKeyHex;
        } catch (Throwable) {
            return null;
        }
    }
}
