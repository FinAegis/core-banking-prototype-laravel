<?php

declare(strict_types=1);

namespace App\Domain\Banking\Contracts;

use App\Models\User;
use App\Domain\Banking\Models\BankConnection;
use App\Domain\Banking\Models\BankAccount;
use App\Domain\Banking\Models\BankTransfer;
use Illuminate\Support\Collection;

interface IBankIntegrationService
{
    /**
     * Register a new bank connector
     * 
     * @param string $bankCode Unique bank identifier
     * @param IBankConnector $connector Bank connector implementation
     */
    public function registerConnector(string $bankCode, IBankConnector $connector): void;
    
    /**
     * Get a registered bank connector
     * 
     * @param string $bankCode Bank identifier
     * @return IBankConnector
     * @throws \App\Domain\Banking\Exceptions\BankNotFoundException
     */
    public function getConnector(string $bankCode): IBankConnector;
    
    /**
     * Get all available bank connectors
     * 
     * @return Collection<string, IBankConnector>
     */
    public function getAvailableConnectors(): Collection;
    
    /**
     * Connect a user to a bank
     * 
     * @param User $user
     * @param string $bankCode
     * @param array $credentials Bank-specific credentials
     * @return BankConnection
     */
    public function connectUserToBank(User $user, string $bankCode, array $credentials): BankConnection;
    
    /**
     * Disconnect a user from a bank
     * 
     * @param User $user
     * @param string $bankCode
     * @return bool
     */
    public function disconnectUserFromBank(User $user, string $bankCode): bool;
    
    /**
     * Get user's bank connections
     * 
     * @param User $user
     * @return Collection<BankConnection>
     */
    public function getUserBankConnections(User $user): Collection;
    
    /**
     * Create a bank account for a user
     * 
     * @param User $user
     * @param string $bankCode
     * @param array $accountDetails
     * @return BankAccount
     */
    public function createBankAccount(User $user, string $bankCode, array $accountDetails): BankAccount;
    
    /**
     * Get user's bank accounts
     * 
     * @param User $user
     * @param string|null $bankCode Filter by bank
     * @return Collection<BankAccount>
     */
    public function getUserBankAccounts(User $user, ?string $bankCode = null): Collection;
    
    /**
     * Sync bank accounts for a user
     * 
     * @param User $user
     * @param string $bankCode
     * @return Collection<BankAccount>
     */
    public function syncBankAccounts(User $user, string $bankCode): Collection;
    
    /**
     * Initiate a transfer between banks
     * 
     * @param User $user
     * @param string $fromBankCode
     * @param string $fromAccountId
     * @param string $toBankCode
     * @param string $toAccountId
     * @param float $amount
     * @param string $currency
     * @param array $metadata
     * @return BankTransfer
     */
    public function initiateInterBankTransfer(
        User $user,
        string $fromBankCode,
        string $fromAccountId,
        string $toBankCode,
        string $toAccountId,
        float $amount,
        string $currency,
        array $metadata = []
    ): BankTransfer;
    
    /**
     * Get optimal bank for a transaction
     * 
     * @param User $user
     * @param string $currency
     * @param float $amount
     * @param string $transferType
     * @return string Bank code
     */
    public function getOptimalBank(User $user, string $currency, float $amount, string $transferType): string;
    
    /**
     * Check bank health status
     * 
     * @param string $bankCode
     * @return array Health status details
     */
    public function checkBankHealth(string $bankCode): array;
    
    /**
     * Get aggregated balance across all banks
     * 
     * @param User $user
     * @param string $currency
     * @return float
     */
    public function getAggregatedBalance(User $user, string $currency): float;
}