<?php

namespace FinAegis\Resources;

use FinAegis\Models\ExchangeRate;
use FinAegis\Models\PaginatedResponse;

class ExchangeRates extends BaseResource
{
    /**
     * List all exchange rates.
     *
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return PaginatedResponse
     */
    public function list(int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->get('/exchange-rates', ['page' => $page, 'per_page' => $perPage]);
        return new PaginatedResponse($response, ExchangeRate::class);
    }
    
    /**
     * Get exchange rate between two assets.
     *
     * @param string $fromAsset Source asset code
     * @param string $toAsset Target asset code
     * @return ExchangeRate
     */
    public function get(string $fromAsset, string $toAsset): ExchangeRate
    {
        $response = $this->get("/exchange-rates/{$fromAsset}/{$toAsset}");
        return new ExchangeRate($response['data']);
    }
    
    /**
     * Convert amount between two assets.
     *
     * @param string $fromAsset Source asset code
     * @param string $toAsset Target asset code
     * @param float $amount Amount to convert
     * @return array
     */
    public function convert(string $fromAsset, string $toAsset, float $amount): array
    {
        $response = $this->get("/exchange-rates/{$fromAsset}/{$toAsset}/convert", ['amount' => $amount]);
        return $response['data'];
    }
    
    /**
     * Refresh all exchange rates.
     *
     * @return array
     */
    public function refresh(): array
    {
        $response = $this->post('/exchange-rates/refresh');
        return $response['data'];
    }
}