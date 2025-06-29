<?php

namespace FinAegis\Models;

class Transfer extends BaseModel
{
    /**
     * Get transfer UUID.
     */
    public function getUuid(): ?string
    {
        return $this->uuid;
    }
    
    /**
     * Get source account UUID.
     */
    public function getFromAccount(): ?string
    {
        return $this->from_account;
    }
    
    /**
     * Get destination account UUID.
     */
    public function getToAccount(): ?string
    {
        return $this->to_account;
    }
    
    /**
     * Get transfer amount.
     */
    public function getAmount(): ?float
    {
        return $this->amount;
    }
    
    /**
     * Get asset code.
     */
    public function getAssetCode(): ?string
    {
        return $this->asset_code;
    }
    
    /**
     * Get transfer status.
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }
    
    /**
     * Get reference.
     */
    public function getReference(): ?string
    {
        return $this->reference;
    }
    
    /**
     * Check if transfer is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
    
    /**
     * Check if transfer is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    
    /**
     * Check if transfer is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}