<?php

namespace FinAegis\Resources;

use FinAegis\Models\Basket;
use FinAegis\Models\PaginatedResponse;

class Baskets extends BaseResource
{
    /**
     * Get basket information.
     *
     * @param string $basketCode Basket code
     * @return Basket
     */
    public function get(string $basketCode): Basket
    {
        $response = $this->get("/baskets/{$basketCode}");
        return new Basket($response['data']);
    }
    
    /**
     * Get basket value history.
     *
     * @param string $basketCode Basket code
     * @param string $period Time period
     * @param string $interval Data interval
     * @return array
     */
    public function getHistory(string $basketCode, string $period = '30d', string $interval = 'daily'): array
    {
        $response = $this->get("/baskets/{$basketCode}/history", [
            'period' => $period,
            'interval' => $interval
        ]);
        return $response['data'];
    }
    
    /**
     * Create a custom basket.
     *
     * @param string $code Basket code
     * @param string $name Basket name
     * @param array $composition Basket composition
     * @return Basket
     */
    public function create(string $code, string $name, array $composition): Basket
    {
        $response = $this->post('/baskets', [
            'code' => $code,
            'name' => $name,
            'composition' => $composition,
        ]);
        return new Basket($response['data']);
    }
    
    /**
     * Compose basket tokens.
     *
     * @param string $accountId Account UUID
     * @param string $basketCode Basket code
     * @param float $amount Amount of basket tokens to compose
     * @return array
     */
    public function compose(string $accountId, string $basketCode, float $amount): array
    {
        $response = $this->post("/baskets/{$basketCode}/compose", [
            'account_id' => $accountId,
            'amount' => $amount,
        ]);
        return $response['data'];
    }
    
    /**
     * Decompose basket tokens.
     *
     * @param string $accountId Account UUID
     * @param string $basketCode Basket code
     * @param float $amount Amount of basket tokens to decompose
     * @return array
     */
    public function decompose(string $accountId, string $basketCode, float $amount): array
    {
        $response = $this->post("/baskets/{$basketCode}/decompose", [
            'account_id' => $accountId,
            'amount' => $amount,
        ]);
        return $response['data'];
    }
}