<?php

namespace FinAegis\Resources;

use FinAegis\Models\ExchangeRate;
use FinAegis\Models\PaginatedResponse;

class ExchangeRates extends BaseResource
{
    /**
     * List all exchange rates.
     *
     * @param  int  $page  Page number
     * @param  int  $perPage  Items per page
     */
    public function list(int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->httpGet('/exchange-rates', ['page' => $page, 'per_page' => $perPage]);

        return new PaginatedResponse($response, ExchangeRate::class);
    }

    /**
     * Get exchange rate between two assets.
     *
     * @param  string  $fromAsset  Source asset code
     * @param  string  $toAsset  Target asset code
     */
    public function get(string $fromAsset, string $toAsset): ExchangeRate
    {
        $response = $this->httpGet("/exchange-rates/{$fromAsset}/{$toAsset}");

        return new ExchangeRate($response['data']);
    }

    /**
     * Convert amount between two assets.
     *
     * @param  string  $fromAsset  Source asset code
     * @param  string  $toAsset  Target asset code
     * @param  float  $amount  Amount to convert
     */
    public function convert(string $fromAsset, string $toAsset, float $amount): array
    {
        $response = $this->httpGet("/exchange-rates/{$fromAsset}/{$toAsset}/convert", ['amount' => $amount]);

        return $response['data'];
    }

    /**
     * Refresh all exchange rates.
     *
     * @deprecated The server has no such route (it never worked and was removed in FinAegis v7.18.4). This method will be removed in the next major version.
     */
    public function refresh(): array
    {
        $response = $this->httpPost('/exchange-rates/refresh');

        return $response['data'];
    }
}
