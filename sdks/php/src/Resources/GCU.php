<?php

namespace FinAegis\Resources;

use FinAegis\Models\GCUInfo;

class GCU extends BaseResource
{
    /**
     * Get GCU information.
     *
     * @return GCUInfo
     */
    public function getInfo(): GCUInfo
    {
        $response = $this->get('/gcu');
        return new GCUInfo($response['data']);
    }
    
    /**
     * Get real-time GCU composition.
     *
     * @return GCUInfo
     */
    public function getComposition(): GCUInfo
    {
        $response = $this->get('/gcu/composition');
        return new GCUInfo($response['data']);
    }
    
    /**
     * Get GCU value history.
     *
     * @param string $period Time period ('24h', '7d', '30d', '90d', '1y', 'all')
     * @param string $interval Data interval ('hourly', 'daily', 'weekly', 'monthly')
     * @return array
     */
    public function getValueHistory(string $period = '30d', string $interval = 'daily'): array
    {
        $response = $this->get('/gcu/value-history', ['period' => $period, 'interval' => $interval]);
        return $response['data'];
    }
    
    /**
     * Get active governance polls.
     *
     * @return array
     */
    public function getActivePolls(): array
    {
        $response = $this->get('/gcu/governance/active-polls');
        return $response['data'];
    }
    
    /**
     * Get supported banks for GCU operations.
     *
     * @return array
     */
    public function getSupportedBanks(): array
    {
        $response = $this->get('/gcu/supported-banks');
        return $response['data'];
    }
}