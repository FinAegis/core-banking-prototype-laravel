<?php

namespace FinAegis\Models;

class Account extends BaseModel
{
    /**
     * Get account UUID.
     */
    public function getUuid(): ?string
    {
        return $this->uuid;
    }
    
    /**
     * Get account name.
     */
    public function getName(): ?string
    {
        return $this->name;
    }
    
    /**
     * Get account balance.
     */
    public function getBalance(): ?float
    {
        return $this->balance;
    }
    
    /**
     * Get account status.
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }
    
    /**
     * Check if account is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    
    /**
     * Check if account is frozen.
     */
    public function isFrozen(): bool
    {
        return $this->status === 'frozen';
    }
    
    /**
     * Check if account is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}