<?php

namespace App\Domain\Wallet\Contracts;

use App\Domain\Wallet\ValueObjects\AddressData;
use App\Domain\Wallet\ValueObjects\BalanceData;
use App\Domain\Wallet\ValueObjects\GasEstimate;
use App\Domain\Wallet\ValueObjects\TransactionData;
use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionResult;

interface BlockchainConnector
{
    /**
     * Generate a new blockchain address
     */
    public function generateAddress(string $publicKey): AddressData;
    
    /**
     * Get balance for an address
     */
    public function getBalance(string $address): BalanceData;
    
    /**
     * Get token balances for an address
     */
    public function getTokenBalances(string $address): array;
    
    /**
     * Estimate gas for a transaction
     */
    public function estimateGas(TransactionData $transaction): GasEstimate;
    
    /**
     * Broadcast a signed transaction to the network
     */
    public function broadcastTransaction(SignedTransaction $transaction): TransactionResult;
    
    /**
     * Get transaction details by hash
     */
    public function getTransaction(string $hash): ?TransactionData;
    
    /**
     * Get current gas prices
     */
    public function getGasPrices(): array;
    
    /**
     * Subscribe to events for an address
     */
    public function subscribeToEvents(string $address, callable $callback): void;
    
    /**
     * Unsubscribe from events
     */
    public function unsubscribeFromEvents(string $address): void;
    
    /**
     * Get the blockchain identifier
     */
    public function getChainId(): string;
    
    /**
     * Check if the connector is healthy
     */
    public function isHealthy(): bool;
}