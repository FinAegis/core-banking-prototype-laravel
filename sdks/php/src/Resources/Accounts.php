<?php

namespace FinAegis\Resources;

use FinAegis\Models\Account;
use FinAegis\Models\Balance;
use FinAegis\Models\PaginatedResponse;
use FinAegis\Models\Transaction;
use FinAegis\Models\Transfer;

class Accounts extends BaseResource
{
    /**
     * List all accounts.
     *
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return PaginatedResponse
     */
    public function list(int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->get('/accounts', ['page' => $page, 'per_page' => $perPage]);
        return new PaginatedResponse($response, Account::class);
    }
    
    /**
     * Create a new account.
     *
     * @param string $userUuid User UUID
     * @param string $name Account name
     * @param float $initialBalance Initial balance in cents
     * @param string $assetCode Asset code (default: USD)
     * @return Account
     */
    public function create(string $userUuid, string $name, float $initialBalance = 0, string $assetCode = 'USD'): Account
    {
        $response = $this->post('/accounts', [
            'user_uuid' => $userUuid,
            'name' => $name,
            'initial_balance' => $initialBalance,
            'asset_code' => $assetCode,
        ]);
        
        return new Account($response['data']);
    }
    
    /**
     * Get account details.
     *
     * @param string $accountId Account UUID
     * @return Account
     */
    public function get(string $accountId): Account
    {
        $response = $this->get("/accounts/{$accountId}");
        return new Account($response['data']);
    }
    
    /**
     * Update account details.
     *
     * @param string $accountId Account UUID
     * @param array $data Update data
     * @return Account
     */
    public function update(string $accountId, array $data): Account
    {
        $response = $this->put("/accounts/{$accountId}", $data);
        return new Account($response['data']);
    }
    
    /**
     * Get account balances.
     *
     * @param string $accountId Account UUID
     * @return array
     */
    public function getBalances(string $accountId): array
    {
        $response = $this->get("/accounts/{$accountId}/balances");
        return $response;
    }
    
    /**
     * Deposit funds to account.
     *
     * @param string $accountId Account UUID
     * @param float $amount Amount in cents
     * @param string $assetCode Asset code
     * @param string|null $reference Optional reference
     * @return Transaction
     */
    public function deposit(string $accountId, float $amount, string $assetCode, ?string $reference = null): Transaction
    {
        $data = [
            'amount' => $amount,
            'asset_code' => $assetCode,
        ];
        
        if ($reference !== null) {
            $data['reference'] = $reference;
        }
        
        $response = $this->post("/accounts/{$accountId}/deposit", $data);
        return new Transaction($response['data']);
    }
    
    /**
     * Withdraw funds from account.
     *
     * @param string $accountId Account UUID
     * @param float $amount Amount in cents
     * @param string $assetCode Asset code
     * @param string|null $reference Optional reference
     * @return Transaction
     */
    public function withdraw(string $accountId, float $amount, string $assetCode, ?string $reference = null): Transaction
    {
        $data = [
            'amount' => $amount,
            'asset_code' => $assetCode,
        ];
        
        if ($reference !== null) {
            $data['reference'] = $reference;
        }
        
        $response = $this->post("/accounts/{$accountId}/withdraw", $data);
        return new Transaction($response['data']);
    }
    
    /**
     * Freeze account.
     *
     * @param string $accountId Account UUID
     * @param string $reason Freeze reason
     * @return Account
     */
    public function freeze(string $accountId, string $reason): Account
    {
        $response = $this->post("/accounts/{$accountId}/freeze", ['reason' => $reason]);
        return new Account($response['data']);
    }
    
    /**
     * Unfreeze account.
     *
     * @param string $accountId Account UUID
     * @param string $reason Unfreeze reason
     * @return Account
     */
    public function unfreeze(string $accountId, string $reason): Account
    {
        $response = $this->post("/accounts/{$accountId}/unfreeze", ['reason' => $reason]);
        return new Account($response['data']);
    }
    
    /**
     * Close account.
     *
     * @param string $accountId Account UUID
     * @param string $reason Close reason
     * @return Account
     */
    public function close(string $accountId, string $reason): Account
    {
        $response = $this->post("/accounts/{$accountId}/close", ['reason' => $reason]);
        return new Account($response['data']);
    }
    
    /**
     * Get account transactions.
     *
     * @param string $accountId Account UUID
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return PaginatedResponse
     */
    public function getTransactions(string $accountId, int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->get("/accounts/{$accountId}/transactions", ['page' => $page, 'per_page' => $perPage]);
        return new PaginatedResponse($response, Transaction::class);
    }
    
    /**
     * Get account transfers.
     *
     * @param string $accountId Account UUID
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return PaginatedResponse
     */
    public function getTransfers(string $accountId, int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->get("/accounts/{$accountId}/transfers", ['page' => $page, 'per_page' => $perPage]);
        return new PaginatedResponse($response, Transfer::class);
    }
}