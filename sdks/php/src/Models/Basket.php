<?php

namespace FinAegis\Models;

class Basket extends BaseModel
{
    /**
     * Get basket code.
     */
    public function getCode(): ?string
    {
        return $this->code;
    }
    
    /**
     * Get basket name.
     */
    public function getName(): ?string
    {
        return $this->name;
    }
    
    /**
     * Get basket composition.
     */
    public function getComposition(): ?array
    {
        return $this->composition;
    }
    
    /**
     * Get total supply.
     */
    public function getTotalSupply(): ?float
    {
        return $this->total_supply;
    }
    
    /**
     * Get current value.
     */
    public function getCurrentValue(): ?float
    {
        return $this->current_value;
    }
    
    /**
     * Check if basket is active.
     */
    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }
}