<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\SmartAccountFactoryInterface;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Demo implementation of smart account factory.
 *
 * Computes deterministic addresses without blockchain connectivity.
 * In production, this would call actual factory contracts.
 */
class DemoSmartAccountFactory implements SmartAccountFactoryInterface
{
    /**
     * Supported networks with their demo factory addresses.
     *
     * @var array<string, string>
     */
    private const DEMO_FACTORY_ADDRESSES = [
        'polygon'  => '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789',
        'base'     => '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789',
        'arbitrum' => '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789',
    ];

    private const CACHE_PREFIX = 'smart_account_deployed_';

    public function computeAddress(string $ownerAddress, string $network, int $salt = 0): string
    {
        // Validate inputs
        $this->validateOwnerAddress($ownerAddress);
        $this->validateNetwork($network);

        // Compute deterministic address using CREATE2 formula
        // address = keccak256(0xff + factory + salt + keccak256(initCode))[12:]
        $initCodeHash = $this->computeInitCodeHash($ownerAddress, $salt);
        $factoryAddress = $this->getFactoryAddress($network);

        if ($factoryAddress === null) {
            throw new InvalidArgumentException("No factory address for network: {$network}");
        }

        $saltBytes = str_pad(dechex($salt), 64, '0', STR_PAD_LEFT);

        // Demo: compute a deterministic address
        $preImage = '0xff' . substr($factoryAddress, 2) . $saltBytes . $initCodeHash;
        $binaryData = hex2bin(substr($preImage, 2));

        if ($binaryData === false) {
            throw new InvalidArgumentException('Invalid hex data for address computation');
        }

        $hash = hash('sha3-256', $binaryData);

        return '0x' . substr($hash, 24); // Take last 20 bytes (40 hex chars)
    }

    public function getInitCode(string $ownerAddress, string $network, int $salt = 0): string
    {
        $this->validateOwnerAddress($ownerAddress);
        $this->validateNetwork($network);

        // Demo init code structure:
        // factory address (20 bytes) + createAccount selector (4 bytes) + owner (32 bytes) + salt (32 bytes)
        $factoryAddress = $this->getFactoryAddress($network);
        $createAccountSelector = 'createAccount'; // In production: actual function selector

        // Build encoded init code (simplified)
        $paddedOwner = str_pad(substr($ownerAddress, 2), 64, '0', STR_PAD_LEFT);
        $paddedSalt = str_pad(dechex($salt), 64, '0', STR_PAD_LEFT);

        // Demo init code: factory address + call data
        return $factoryAddress . hash('sha3-256', $createAccountSelector . $paddedOwner . $paddedSalt);
    }

    public function isDeployed(string $accountAddress, string $network): bool
    {
        // Demo: track deployment status in cache
        $cacheKey = self::CACHE_PREFIX . $network . '_' . strtolower($accountAddress);

        return Cache::get($cacheKey, false);
    }

    public function getFactoryAddress(string $network): ?string
    {
        // First check config, then fall back to demo addresses
        $configKey = strtoupper($network) . '_FACTORY_ADDRESS';
        $configured = config("relayer.smart_accounts.factory_addresses.{$network}");

        if ($configured) {
            return $configured;
        }

        return self::DEMO_FACTORY_ADDRESSES[$network] ?? null;
    }

    public function supportsNetwork(string $network): bool
    {
        return in_array($network, $this->getSupportedNetworks(), true);
    }

    public function getSupportedNetworks(): array
    {
        return array_keys(self::DEMO_FACTORY_ADDRESSES);
    }

    /**
     * Mark an account as deployed (for testing).
     */
    public function markAsDeployed(string $accountAddress, string $network): void
    {
        $cacheKey = self::CACHE_PREFIX . $network . '_' . strtolower($accountAddress);
        Cache::put($cacheKey, true, now()->addDays(30));
    }

    /**
     * Validate owner address format.
     */
    private function validateOwnerAddress(string $address): void
    {
        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            throw new InvalidArgumentException('Invalid owner address format');
        }
    }

    /**
     * Validate network is supported.
     */
    private function validateNetwork(string $network): void
    {
        if (! $this->supportsNetwork($network)) {
            throw new InvalidArgumentException(
                "Unsupported network: {$network}. Supported: " . implode(', ', $this->getSupportedNetworks())
            );
        }
    }

    /**
     * Compute init code hash for address derivation.
     */
    private function computeInitCodeHash(string $ownerAddress, int $salt): string
    {
        $initCode = $ownerAddress . '_' . $salt;

        return hash('sha3-256', $initCode);
    }
}
