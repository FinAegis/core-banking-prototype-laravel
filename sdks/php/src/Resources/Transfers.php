<?php

namespace FinAegis\Resources;

use FinAegis\Models\PaginatedResponse;
use FinAegis\Models\Transfer;

class Transfers extends BaseResource
{
    /**
     * Create a new transfer.
     *
     * The request carries an Idempotency-Key, so a retry does not create a
     * second transfer. Pass $idempotencyKey to choose the key yourself, for
     * example to repeat the same transfer safely after a restart.
     *
     * @param  string  $fromAccount  Source account UUID
     * @param  string  $toAccount  Destination account UUID
     * @param  float  $amount  Amount in major units of the asset, for example 25.50 for 25.50 US dollars
     * @param  string  $assetCode  Asset code
     * @param  string|null  $reference  Optional reference
     * @param  string|null  $idempotencyKey  Optional Idempotency-Key
     */
    public function create(
        string $fromAccount,
        string $toAccount,
        float $amount,
        string $assetCode,
        ?string $reference = null,
        ?string $idempotencyKey = null
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

        $response = $this->httpPost('/transfers', $data, $idempotencyKey);

        return new Transfer($response['data']);
    }

    /**
     * Get transfer details.
     *
     * @param  string  $transferId  Transfer UUID
     */
    public function get(string $transferId): Transfer
    {
        $response = $this->httpGet("/transfers/{$transferId}");

        return new Transfer($response['data']);
    }

    /**
     * List transfers.
     *
     * @param  int  $page  Page number
     * @param  int  $perPage  Items per page
     */
    public function list(int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->httpGet('/transfers', ['page' => $page, 'per_page' => $perPage]);

        return new PaginatedResponse($response, Transfer::class);
    }
}
