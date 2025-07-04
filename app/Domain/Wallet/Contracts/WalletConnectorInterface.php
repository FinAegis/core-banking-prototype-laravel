<?php

namespace App\Domain\Wallet\Contracts;

use App\Domain\Wallet\ValueObjects\BlockchainTransaction;
use App\Domain\Wallet\ValueObjects\WalletAddress;

interface WalletConnectorInterface
{
    /**
     * Generate a new wallet address
     *
     * @param string $blockchain
     * @param string $accountId
     * @return WalletAddress
     */
    public function generateAddress(string $blockchain, string $accountId): WalletAddress;

    /**
     * Get wallet balance from blockchain
     *
     * @param string $blockchain
     * @param string $address
     * @return array
     */
    public function getBalance(string $blockchain, string $address): array;

    /**
     * Send transaction to blockchain
     *
     * @param string $blockchain
     * @param string $fromAddress
     * @param string $toAddress
     * @param string $amount
     * @param array $options
     * @return BlockchainTransaction
     */
    public function sendTransaction(
        string $blockchain,
        string $fromAddress,
        string $toAddress,
        string $amount,
        array $options = []
    ): BlockchainTransaction;

    /**
     * Get transaction status
     *
     * @param string $blockchain
     * @param string $transactionHash
     * @return array
     */
    public function getTransactionStatus(string $blockchain, string $transactionHash): array;

    /**
     * Monitor incoming transactions
     *
     * @param string $blockchain
     * @param string $address
     * @param int $fromBlock
     * @return array
     */
    public function monitorIncomingTransactions(
        string $blockchain,
        string $address,
        int $fromBlock = 0
    ): array;

    /**
     * Validate address format
     *
     * @param string $blockchain
     * @param string $address
     * @return bool
     */
    public function validateAddress(string $blockchain, string $address): bool;

    /**
     * Get network fee estimate
     *
     * @param string $blockchain
     * @param string $priority
     * @return array
     */
    public function estimateNetworkFee(string $blockchain, string $priority = 'medium'): array;

    /**
     * Get supported blockchains
     *
     * @return array
     */
    public function getSupportedBlockchains(): array;
}