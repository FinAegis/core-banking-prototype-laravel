<?php

namespace FinAegis\Models;

class ExchangeRate extends BaseModel
{
    /**
     * Get from asset code.
     */
    public function getFromAsset(): ?string
    {
        return $this->from_asset;
    }
    
    /**
     * Get to asset code.
     */
    public function getToAsset(): ?string
    {
        return $this->to_asset;
    }
    
    /**
     * Get exchange rate.
     */
    public function getRate(): ?float
    {
        return $this->rate;
    }
    
    /**
     * Get rate type.
     */
    public function getRateType(): ?string
    {
        return $this->rate_type;
    }
    
    /**
     * Get last updated timestamp.
     */
    public function getLastUpdated(): ?string
    {
        return $this->last_updated;
    }
}