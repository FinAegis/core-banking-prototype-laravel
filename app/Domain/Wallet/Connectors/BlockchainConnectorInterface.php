<?php

namespace App\Domain\Wallet\Connectors;

use App\Domain\Wallet\ValueObjects\AddressData;
use App\Domain\Wallet\ValueObjects\TransactionData;

interface BlockchainConnectorInterface
{
    /**
     * Generate a new blockchain address
     */
    public function generateAddress(string $publicKey): AddressData;
    
    /**
     * Validate a blockchain address
     */
    public function validateAddress(string $address): bool;
    
    /**
     * Get balance for an address
     */
    public function getBalance(string $address): string;
    
    /**
     * Get token balance for an address
     */
    public function getTokenBalance(string $address, string $tokenAddress): string;
    
    /**
     * Get transaction details
     */
    public function getTransaction(string $transactionHash): array;
    
    /**
     * Prepare a transaction (unsigned)
     */
    public function prepareTransaction(string $from, string $to, string $amount): array;
    
    /**
     * Prepare a token transfer transaction
     */
    public function prepareTokenTransfer(string $from, string $to, string $tokenAddress, string $amount): array;
    
    /**
     * Sign a transaction
     */
    public function signTransaction(array $transaction, string $privateKey): array;
    
    /**
     * Broadcast a signed transaction
     */
    public function broadcastTransaction(array $signedTransaction): string;
    
    /**
     * Estimate gas for a transaction
     */
    public function estimateGas(string $from, string $to, string $amount): string;
    
    /**
     * Get token information
     */
    public function getTokenInfo(string $tokenAddress): array;
    
    /**
     * Get current block number
     */
    public function getCurrentBlockNumber(): int;
}