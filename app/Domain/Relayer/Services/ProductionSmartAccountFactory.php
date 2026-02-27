<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\SmartAccountFactoryInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\RpcException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use kornrunner\Keccak;

/**
 * Production smart account factory using Ethereum keccak256 and on-chain verification.
 *
 * Key differences from DemoSmartAccountFactory:
 * - Uses kornrunner\Keccak::hash() (Ethereum keccak256) instead of PHP's hash('sha3-256')
 *   which is NIST SHA3-256 — NOT the same as Ethereum's keccak256
 * - Checks deployment status via eth_getCode RPC call instead of cache
 * - Reads factory addresses dynamically from config
 */
class ProductionSmartAccountFactory implements SmartAccountFactoryInterface
{
    /**
     * createAccount(address,uint256) function selector.
     * keccak256("createAccount(address,uint256)")[0:4].
     */
    private const CREATE_ACCOUNT_SELECTOR = '5fbfb9cf';

    public function __construct(
        private readonly EthRpcClient $rpcClient,
    ) {
    }

    public function computeAddress(string $ownerAddress, string $network, int $salt = 0): string
    {
        $this->validateOwnerAddress($ownerAddress);
        $this->validateNetwork($network);

        $factoryAddress = $this->getFactoryAddress($network);
        if ($factoryAddress === null || $factoryAddress === '') {
            throw new InvalidArgumentException("No factory address configured for network: {$network}");
        }

        // CREATE2: address = keccak256(0xff ++ factory ++ salt ++ keccak256(initCode))[12:]
        $initCodeHash = $this->computeInitCodeHash($ownerAddress, $salt);
        $saltBytes = str_pad(dechex($salt), 64, '0', STR_PAD_LEFT);

        // Build pre-image: 0xff + factory (20 bytes) + salt (32 bytes) + initCodeHash (32 bytes)
        $preImage = 'ff' . substr(strtolower($factoryAddress), 2) . $saltBytes . $initCodeHash;

        $hash = Keccak::hash(hex2bin($preImage), 256);

        // Take last 20 bytes (40 hex chars)
        return '0x' . substr($hash, 24);
    }

    public function getInitCode(string $ownerAddress, string $network, int $salt = 0): string
    {
        $this->validateOwnerAddress($ownerAddress);
        $this->validateNetwork($network);

        $factoryAddress = $this->getFactoryAddress($network);
        if ($factoryAddress === null || $factoryAddress === '') {
            throw new InvalidArgumentException("No factory address configured for network: {$network}");
        }

        // initCode = factory address (20 bytes) + createAccount calldata
        // calldata = selector (4 bytes) + abi-encoded (address, uint256)
        $paddedOwner = str_pad(substr(strtolower($ownerAddress), 2), 64, '0', STR_PAD_LEFT);
        $paddedSalt = str_pad(dechex($salt), 64, '0', STR_PAD_LEFT);

        return strtolower($factoryAddress) . self::CREATE_ACCOUNT_SELECTOR . $paddedOwner . $paddedSalt;
    }

    /**
     * Check if a smart account is deployed by querying eth_getCode.
     *
     * A deployed contract has bytecode; an EOA or undeployed CREATE2 returns '0x'.
     */
    public function isDeployed(string $accountAddress, string $network): bool
    {
        $this->validateNetwork($network);

        $supported = SupportedNetwork::from($network);

        try {
            $code = $this->rpcClient->getCode($supported, $accountAddress);

            // '0x' or empty means no code deployed
            return $code !== '0x' && $code !== '' && $code !== '0x0';
        } catch (RpcException $e) {
            Log::warning('Failed to check deployment status via RPC, assuming not deployed', [
                'address' => $accountAddress,
                'network' => $network,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getFactoryAddress(string $network): ?string
    {
        $configured = config("relayer.smart_accounts.factory_addresses.{$network}");

        if (! empty($configured)) {
            return (string) $configured;
        }

        return null;
    }

    public function supportsNetwork(string $network): bool
    {
        return in_array($network, $this->getSupportedNetworks(), true);
    }

    /**
     * Get supported networks dynamically from config (only those with factory addresses).
     *
     * @return array<int, string>
     */
    public function getSupportedNetworks(): array
    {
        $factoryAddresses = (array) config('relayer.smart_accounts.factory_addresses', []);

        return array_keys(array_filter($factoryAddresses, fn ($address) => ! empty($address)));
    }

    private function validateOwnerAddress(string $address): void
    {
        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            throw new InvalidArgumentException('Invalid owner address format');
        }
    }

    private function validateNetwork(string $network): void
    {
        if (SupportedNetwork::tryFrom($network) === null) {
            throw new InvalidArgumentException(
                "Unsupported network: {$network}. Valid networks: " . implode(', ', array_column(SupportedNetwork::cases(), 'value'))
            );
        }
    }

    /**
     * Compute init code hash using Ethereum keccak256.
     */
    private function computeInitCodeHash(string $ownerAddress, int $salt): string
    {
        $paddedOwner = str_pad(substr(strtolower($ownerAddress), 2), 64, '0', STR_PAD_LEFT);
        $paddedSalt = str_pad(dechex($salt), 64, '0', STR_PAD_LEFT);

        $initCode = self::CREATE_ACCOUNT_SELECTOR . $paddedOwner . $paddedSalt;

        return Keccak::hash(hex2bin($initCode), 256);
    }
}
