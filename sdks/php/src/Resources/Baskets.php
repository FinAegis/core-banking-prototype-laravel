<?php

namespace FinAegis\Resources;

use FinAegis\Models\Basket;

class Baskets extends BaseResource
{
    /**
     * Get basket information.
     *
     * @param  string  $basketCode  Basket code
     */
    public function get(string $basketCode): Basket
    {
        $response = $this->httpGet("/baskets/{$basketCode}");

        return new Basket($response['data']);
    }

    /**
     * Get basket value history.
     *
     * @param  string  $basketCode  Basket code
     * @param  string  $period  Time period
     * @param  string  $interval  Data interval
     */
    public function getHistory(string $basketCode, string $period = '30d', string $interval = 'daily'): array
    {
        $response = $this->httpGet("/baskets/{$basketCode}/history", [
            'period' => $period,
            'interval' => $interval,
        ]);

        return $response['data'];
    }

    /**
     * Create a custom basket.
     *
     * @param  string  $code  Basket code
     * @param  string  $name  Basket name
     * @param  array  $composition  Basket composition
     */
    public function create(string $code, string $name, array $composition): Basket
    {
        $response = $this->httpPost('/baskets', [
            'code' => $code,
            'name' => $name,
            'composition' => $composition,
        ]);

        return new Basket($response['data']);
    }

    /**
     * Compose basket tokens.
     *
     * @param  string  $accountId  Account UUID
     * @param  string  $basketCode  Basket code
     * @param  float  $amount  Amount of basket tokens to compose
     */
    public function compose(string $accountId, string $basketCode, float $amount): array
    {
        $response = $this->httpPost("/baskets/{$basketCode}/compose", [
            'account_id' => $accountId,
            'amount' => $amount,
        ]);

        return $response['data'];
    }

    /**
     * Decompose basket tokens.
     *
     * @param  string  $accountId  Account UUID
     * @param  string  $basketCode  Basket code
     * @param  float  $amount  Amount of basket tokens to decompose
     */
    public function decompose(string $accountId, string $basketCode, float $amount): array
    {
        $response = $this->httpPost("/baskets/{$basketCode}/decompose", [
            'account_id' => $accountId,
            'amount' => $amount,
        ]);

        return $response['data'];
    }
}
