<?php

namespace FinAegis\Models;

abstract class BaseModel
{
    /** @var array */
    protected array $attributes = [];
    
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }
    
    /**
     * Get attribute value.
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->attributes[$key] ?? null;
    }
    
    /**
     * Set attribute value.
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }
    
    /**
     * Check if attribute exists.
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
    
    /**
     * Get all attributes.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
    
    /**
     * Convert to JSON.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->attributes);
    }
}