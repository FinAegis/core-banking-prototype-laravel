<?php

namespace App\Domain\Wallet\Contracts;

interface KeyManagementServiceInterface
{
    /**
     * Generate a mnemonic phrase
     *
     * @param int $wordCount
     * @return string
     */
    public function generateMnemonic(int $wordCount = 12): string;

    /**
     * Generate HD wallet from mnemonic
     *
     * @param string $mnemonic
     * @param string|null $passphrase
     * @return array
     */
    public function generateHDWallet(string $mnemonic, ?string $passphrase = null): array;

    /**
     * Derive key pair for specific path
     *
     * @param string $seedHex
     * @param string $path
     * @return array
     */
    public function deriveKeyPair(string $seedHex, string $path): array;

    /**
     * Sign blockchain transaction
     *
     * @param string $privateKey
     * @param array $transaction
     * @param string $blockchain
     * @return string
     */
    public function signTransaction(string $privateKey, array $transaction, string $blockchain): string;

    /**
     * Encrypt wallet seed
     *
     * @param string $seed
     * @param string $password
     * @return string
     */
    public function encryptSeed(string $seed, string $password): string;

    /**
     * Decrypt wallet seed
     *
     * @param string $encryptedSeed
     * @param string $password
     * @return string
     */
    public function decryptSeed(string $encryptedSeed, string $password): string;

    /**
     * Validate mnemonic phrase
     *
     * @param string $mnemonic
     * @return bool
     */
    public function validateMnemonic(string $mnemonic): bool;

    /**
     * Generate wallet backup
     *
     * @param string $walletId
     * @return array
     */
    public function generateBackup(string $walletId): array;

    /**
     * Restore wallet from backup
     *
     * @param array $backup
     * @param string $password
     * @return string
     */
    public function restoreFromBackup(array $backup, string $password): string;

    /**
     * Rotate encryption keys
     *
     * @param string $walletId
     * @param string $oldPassword
     * @param string $newPassword
     * @return void
     */
    public function rotateKeys(string $walletId, string $oldPassword, string $newPassword): void;
}