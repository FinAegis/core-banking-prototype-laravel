<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Contracts;

use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionData;

/**
 * Interface for external transaction signers (hardware wallets, multi-sig, etc.).
 *
 * This interface abstracts the signing process to support various external
 * signing methods including Ledger and Trezor hardware wallets.
 */
interface ExternalSignerInterface
{
    /**
     * Get the signer type identifier.
     */
    public function getType(): string;

    /**
     * Get the list of supported blockchain chains.
     *
     * @return array<string>
     */
    public function getSupportedChains(): array;

    /**
     * Prepare transaction data for signing by the external device.
     *
     * Returns the serialized/encoded data that the device needs to sign.
     *
     * @return array{
     *     raw_data: string,
     *     display_data: array<string, mixed>,
     *     encoding: string
     * }
     */
    public function prepareForSigning(TransactionData $transaction): array;

    /**
     * Construct a signed transaction from the raw signature.
     */
    public function constructSignedTransaction(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): SignedTransaction;

    /**
     * Validate a signature against the transaction data.
     */
    public function validateSignature(
        TransactionData $transaction,
        string $signature,
        string $publicKey
    ): bool;

    /**
     * Get the BIP44 derivation path for a specific chain and account index.
     */
    public function getDerivationPath(string $chain, int $accountIndex = 0): string;

    /**
     * Check if this signer supports a specific chain.
     */
    public function supportsChain(string $chain): bool;

    /**
     * Get the required confirmation steps for this signer type.
     *
     * @return array<string, mixed>
     */
    public function getConfirmationSteps(): array;
}
