<?php

namespace FinAegis\Models;

class Webhook extends BaseModel
{
    /**
     * Get webhook UUID.
     */
    public function getUuid(): ?string
    {
        return $this->uuid;
    }
    
    /**
     * Get webhook name.
     */
    public function getName(): ?string
    {
        return $this->name;
    }
    
    /**
     * Get webhook URL.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }
    
    /**
     * Get subscribed events.
     */
    public function getEvents(): ?array
    {
        return $this->events;
    }
    
    /**
     * Get custom headers.
     */
    public function getHeaders(): ?array
    {
        return $this->headers;
    }
    
    /**
     * Check if webhook is active.
     */
    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }
}