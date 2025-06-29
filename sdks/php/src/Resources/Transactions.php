<?php

namespace FinAegis\Resources;

use FinAegis\Models\Transaction;
use FinAegis\Models\PaginatedResponse;

class Transactions extends BaseResource
{
    /**
     * Get transaction details.
     *
     * @param string $transactionId Transaction UUID
     * @return Transaction
     */
    public function get(string $transactionId): Transaction
    {
        $response = $this->get("/transactions/{$transactionId}");
        return new Transaction($response['data']);
    }
    
    /**
     * List transactions.
     *
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return PaginatedResponse
     */
    public function list(int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->get('/transactions', ['page' => $page, 'per_page' => $perPage]);
        return new PaginatedResponse($response, Transaction::class);
    }
}