<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use App\Domain\Custodian\Contracts\ICustodianConnector;
use App\Domain\Custodian\Exceptions\CustodianNotFoundException;
use App\Domain\Custodian\Exceptions\CustodianNotAvailableException;
use Illuminate\Support\Facades\Log;

class CustodianRegistry
{
    private array $custodians = [];
    private ?string $defaultCustodian = null;

    /**
     * Register a custodian connector
     */
    public function register(string $name, ICustodianConnector $connector): void
    {
        $this->custodians[$name] = $connector;
        
        // Set as default if it's the first one
        if ($this->defaultCustodian === null) {
            $this->defaultCustodian = $name;
        }

        Log::info("Registered custodian: {$name}");
    }

    /**
     * Get a custodian by name
     */
    public function get(string $name): ICustodianConnector
    {
        if (!isset($this->custodians[$name])) {
            throw new CustodianNotFoundException("Custodian '{$name}' not found");
        }

        $custodian = $this->custodians[$name];

        if (!$custodian->isAvailable()) {
            throw new CustodianNotAvailableException("Custodian '{$name}' is not available");
        }

        return $custodian;
    }

    /**
     * Get the default custodian
     */
    public function getDefault(): ICustodianConnector
    {
        if ($this->defaultCustodian === null) {
            throw new CustodianNotFoundException("No default custodian configured");
        }

        return $this->get($this->defaultCustodian);
    }

    /**
     * Set the default custodian
     */
    public function setDefault(string $name): void
    {
        if (!isset($this->custodians[$name])) {
            throw new CustodianNotFoundException("Custodian '{$name}' not found");
        }

        $this->defaultCustodian = $name;
    }

    /**
     * Get all registered custodians
     */
    public function all(): array
    {
        return $this->custodians;
    }

    /**
     * Get available custodians
     */
    public function available(): array
    {
        return array_filter($this->custodians, fn($custodian) => $custodian->isAvailable());
    }

    /**
     * Check if a custodian is registered
     */
    public function has(string $name): bool
    {
        return isset($this->custodians[$name]);
    }

    /**
     * Remove a custodian
     */
    public function remove(string $name): void
    {
        unset($this->custodians[$name]);
        
        if ($this->defaultCustodian === $name) {
            $this->defaultCustodian = null;
        }
    }

    /**
     * Get custodian names
     */
    public function names(): array
    {
        return array_keys($this->custodians);
    }

    /**
     * Find custodians that support a specific asset
     */
    public function findByAsset(string $assetCode): array
    {
        return array_filter($this->custodians, function ($custodian) use ($assetCode) {
            return in_array($assetCode, $custodian->getSupportedAssets());
        });
    }

    /**
     * Alias for get method for backward compatibility
     */
    public function getConnector(string $name): ICustodianConnector
    {
        return $this->get($name);
    }

    /**
     * List custodians as array
     */
    public function listCustodians(): array
    {
        $list = [];
        foreach ($this->custodians as $name => $connector) {
            $list[] = [
                'id' => $name,
                'name' => ucfirst(str_replace('_', ' ', $name)),
            ];
        }
        return $list;
    }
}