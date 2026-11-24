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
     * @param  int  $page  Page number
     * @param  int  $perPage  Items per page
     */
    public function list(int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->httpGet('/accounts', ['page' => $page, 'per_page' => $perPage]);

        return new PaginatedResponse($response, Account::class);
    }

    /**
     * Create a new account.
     *
     * @param  string  $userUuid  User UUID
     * @param  string  $name  Account name
     * @param  float  $initialBalance  Must be 0. Accounts open empty; fund them with a deposit.
     * @param  string  $assetCode  Asset code (default: USD)
     */
    public function create(string $userUuid, string $name, float $initialBalance = 0, string $assetCode = 'USD'): Account
    {
        $response = $this->httpPost('/accounts', [
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
     * @param  string  $accountId  Account UUID
     */
    public function get(string $accountId): Account
    {
        $response = $this->httpGet("/accounts/{$accountId}");

        return new Account($response['data']);
    }

    /**
     * Update account details.
     *
     * @param  string  $accountId  Account UUID
     * @param  array  $data  Update data
     */
    public function update(string $accountId, array $data): Account
    {
        $response = $this->httpPut("/accounts/{$accountId}", $data);

        return new Account($response['data']);
    }

    /**
     * Get account balances.
     *
     * @param  string  $accountId  Account UUID
     */
    public function getBalances(string $accountId): array
    {
        $response = $this->httpGet("/accounts/{$accountId}/balances");

        return $response;
    }

    /**
     * Deposit funds to account.
     *
     * @param  string  $accountId  Account UUID
     * @param  float  $amount  Amount in major units of the asset, for example 100.00 for 100 US dollars
     * @param  string  $assetCode  Asset code
     * @param  string|null  $reference  Optional reference
     * @param  string|null  $idempotencyKey  Optional Idempotency-Key. The client makes one
     *                                         when it is left out and repeats it on retries.
     */
    public function deposit(
        string $accountId,
        float $amount,
        string $assetCode,
        ?string $reference = null,
        ?string $idempotencyKey = null
    ): Transaction {
        $data = [
            'amount' => $amount,
            'asset_code' => $assetCode,
        ];

        if ($reference !== null) {
            $data['reference'] = $reference;
        }

        $response = $this->httpPost("/accounts/{$accountId}/deposit", $data, $idempotencyKey);

        return new Transaction($response['data']);
    }

    /**
     * Withdraw funds from account.
     *
     * @param  string  $accountId  Account UUID
     * @param  float  $amount  Amount in major units of the asset, for example 100.00 for 100 US dollars
     * @param  string  $assetCode  Asset code
     * @param  string|null  $reference  Optional reference
     * @param  string|null  $idempotencyKey  Optional Idempotency-Key. The client makes one
     *                                         when it is left out and repeats it on retries.
     */
    public function withdraw(
        string $accountId,
        float $amount,
        string $assetCode,
        ?string $reference = null,
        ?string $idempotencyKey = null
    ): Transaction {
        $data = [
            'amount' => $amount,
            'asset_code' => $assetCode,
        ];

        if ($reference !== null) {
            $data['reference'] = $reference;
        }

        $response = $this->httpPost("/accounts/{$accountId}/withdraw", $data, $idempotencyKey);

        return new Transaction($response['data']);
    }

    /**
     * Freeze account.
     *
     * @param  string  $accountId  Account UUID
     * @param  string  $reason  Freeze reason
     */
    public function freeze(string $accountId, string $reason): Account
    {
        $response = $this->httpPost("/accounts/{$accountId}/freeze", ['reason' => $reason]);

        return new Account($response['data']);
    }

    /**
     * Unfreeze account.
     *
     * @param  string  $accountId  Account UUID
     * @param  string  $reason  Unfreeze reason
     */
    public function unfreeze(string $accountId, string $reason): Account
    {
        $response = $this->httpPost("/accounts/{$accountId}/unfreeze", ['reason' => $reason]);

        return new Account($response['data']);
    }

    /**
     * Close account.
     *
     * @param  string  $accountId  Account UUID
     * @param  string  $reason  Close reason
     */
    public function close(string $accountId, string $reason): Account
    {
        $response = $this->httpPost("/accounts/{$accountId}/close", ['reason' => $reason]);

        return new Account($response['data']);
    }

    /**
     * Get account transactions.
     *
     * @param  string  $accountId  Account UUID
     * @param  int  $page  Page number
     * @param  int  $perPage  Items per page
     */
    public function getTransactions(string $accountId, int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->httpGet("/accounts/{$accountId}/transactions", ['page' => $page, 'per_page' => $perPage]);

        return new PaginatedResponse($response, Transaction::class);
    }

    /**
     * Get account transfers.
     *
     * @param  string  $accountId  Account UUID
     * @param  int  $page  Page number
     * @param  int  $perPage  Items per page
     */
    public function getTransfers(string $accountId, int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->httpGet("/accounts/{$accountId}/transfers", ['page' => $page, 'per_page' => $perPage]);

        return new PaginatedResponse($response, Transfer::class);
    }
}
