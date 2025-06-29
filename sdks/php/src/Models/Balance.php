<?php

namespace FinAegis\Models;

class Balance extends BaseModel
{
    /**
     * Get asset code.
     */
    public function getAssetCode(): ?string
    {
        return $this->asset_code;
    }
    
    /**
     * Get total balance.
     */
    public function getTotalBalance(): ?float
    {
        return $this->total_balance;
    }
    
    /**
     * Get available balance.
     */
    public function getAvailableBalance(): ?float
    {
        return $this->available_balance;
    }
    
    /**
     * Get reserved balance.
     */
    public function getReservedBalance(): ?float
    {
        return $this->reserved_balance;
    }
}