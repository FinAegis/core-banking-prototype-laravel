<?php

namespace FinAegis\Resources;

use FinAegis\Models\GCUInfo;

class GCU extends BaseResource
{
    /**
     * Get GCU information.
     */
    public function getInfo(): GCUInfo
    {
        $response = $this->httpGet('/gcu');

        return new GCUInfo($response['data']);
    }

    /**
     * Get real-time GCU composition.
     */
    public function getComposition(): GCUInfo
    {
        $response = $this->httpGet('/gcu/composition');

        return new GCUInfo($response['data']);
    }

    /**
     * Get GCU value history.
     *
     * @param  string  $period  Time period ('24h', '7d', '30d', '90d', '1y', 'all')
     * @param  string  $interval  Data interval ('hourly', 'daily', 'weekly', 'monthly')
     */
    public function getValueHistory(string $period = '30d', string $interval = 'daily'): array
    {
        $response = $this->httpGet('/gcu/value-history', ['period' => $period, 'interval' => $interval]);

        return $response['data'];
    }

    /**
     * Get active governance polls.
     */
    public function getActivePolls(): array
    {
        $response = $this->httpGet('/gcu/governance/active-polls');

        return $response['data'];
    }

    /**
     * Get supported banks for GCU operations.
     */
    public function getSupportedBanks(): array
    {
        $response = $this->httpGet('/gcu/supported-banks');

        return $response['data'];
    }
}
