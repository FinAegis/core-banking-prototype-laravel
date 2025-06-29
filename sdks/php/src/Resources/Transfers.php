<?php

namespace FinAegis\Resources;

use FinAegis\Models\Transfer;
use FinAegis\Models\PaginatedResponse;

class Transfers extends BaseResource
{
    /**
     * Create a new transfer.
     *
     * @param string $fromAccount Source account UUID
     * @param string $toAccount Destination account UUID
     * @param float $amount Amount in cents
     * @param string $assetCode Asset code
     * @param string|null $reference Optional reference
     * @return Transfer
     */
    public function create(
        string $fromAccount,
        string $toAccount,
        float $amount,
        string $assetCode,
        ?string $reference = null
    ): Transfer {
        $data = [
            'from_account' => $fromAccount,
            'to_account' => $toAccount,
            'amount' => $amount,
            'asset_code' => $assetCode,
        ];
        
        if ($reference !== null) {
            $data['reference'] = $reference;
        }
        
        $response = $this->post('/transfers', $data);
        return new Transfer($response['data']);
    }
    
    /**
     * Get transfer details.
     *
     * @param string $transferId Transfer UUID
     * @return Transfer
     */
    public function get(string $transferId): Transfer
    {
        $response = $this->get("/transfers/{$transferId}");
        return new Transfer($response['data']);
    }
    
    /**
     * List transfers.
     *
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return PaginatedResponse
     */
    public function list(int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->get('/transfers', ['page' => $page, 'per_page' => $perPage]);
        return new PaginatedResponse($response, Transfer::class);
    }
}