<?php

namespace FinAegis\Resources;

use FinAegis\Models\PaginatedResponse;
use FinAegis\Models\Transaction;

class Transactions extends BaseResource
{
    /**
     * Get transaction details.
     *
     * @param  string  $transactionId  Transaction UUID
     */
    public function get(string $transactionId): Transaction
    {
        $response = $this->httpGet("/transactions/{$transactionId}");

        return new Transaction($response['data']);
    }

    /**
     * List transactions.
     *
     * @param  int  $page  Page number
     * @param  int  $perPage  Items per page
     */
    public function list(int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->httpGet('/transactions', ['page' => $page, 'per_page' => $perPage]);

        return new PaginatedResponse($response, Transaction::class);
    }
}
