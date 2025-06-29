<?php

namespace FinAegis\Models;

class Transaction extends BaseModel
{
    /**
     * Get transaction UUID.
     */
    public function getUuid(): ?string
    {
        return $this->uuid;
    }
    
    /**
     * Get transaction type.
     */
    public function getType(): ?string
    {
        return $this->type;
    }
    
    /**
     * Get transaction amount.
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
     * Get transaction status.
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
     * Check if transaction is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
    
    /**
     * Check if transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    
    /**
     * Check if transaction is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}