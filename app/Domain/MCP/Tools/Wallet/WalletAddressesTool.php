<?php

declare(strict_types=1);

namespace App\Domain\MCP\Tools\Wallet;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * MCP tool that lists the caller's registered blockchain addresses
 * (EVM smart-account mirrors + Solana ed25519) from blockchain_addresses.
 *
 * Lookup is by user_uuid — the FK links to users.uuid, NOT users.id
 * (same pitfall as BridgePostKycHandler / BlockchainAddressBridgeObserver).
 *
 * Read-only — no idempotency_key required.
 */
final class WalletAddressesTool implements MCPToolInterface
{
    public function getName(): string
    {
        return 'wallet.addresses';
    }

    public function getCategory(): string
    {
        return 'wallet';
    }

    public function getDescription(): string
    {
        return 'List the registered blockchain wallet addresses (chain, address, active flag) for the authenticated user.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'chain' => [
                    'type'        => 'string',
                    'description' => 'Optional chain filter (e.g. polygon, base, arbitrum, ethereum, solana).',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'addresses' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'chain'      => ['type' => 'string'],
                            'address'    => ['type' => 'string'],
                            'is_active'  => ['type' => 'boolean'],
                            'label'      => ['type' => ['string', 'null']],
                            'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                ],
                'count' => ['type' => 'integer'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCapabilities(): array
    {
        return ['async' => false];
    }

    public function isCacheable(): bool
    {
        return false;
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function validateInput(array $parameters): bool
    {
        if (isset($parameters['chain']) && (! is_string($parameters['chain']) || $parameters['chain'] === '')) {
            return false;
        }

        return true;
    }

    public function authorize(?string $userId): bool
    {
        return $this->resolveUser($userId) !== null;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        $user = $this->resolveUser(null);
        if ($user === null) {
            return ToolExecutionResult::failure('Unauthenticated: wallet.addresses requires a user-bound bearer token');
        }

        $query = BlockchainAddress::query()
            ->where('user_uuid', $user->uuid)
            ->orderBy('chain')
            ->orderBy('created_at');

        if (isset($parameters['chain']) && is_string($parameters['chain']) && $parameters['chain'] !== '') {
            $query->where('chain', $parameters['chain']);
        }

        $addresses = $query->get()->map(static fn (BlockchainAddress $row): array => [
            'chain'      => $row->chain,
            'address'    => $row->address,
            'is_active'  => $row->is_active,
            'label'      => $row->label,
            'created_at' => $row->created_at?->toIso8601String(),
        ])->values()->all();

        return ToolExecutionResult::success([
            'addresses' => $addresses,
            'count'     => count($addresses),
        ]);
    }

    private function resolveUser(?string $userId): ?User
    {
        if ($userId !== null) {
            $u = User::find($userId);
            if ($u instanceof User) {
                return $u;
            }
        }

        $guarded = Auth::guard('api')->user();
        if ($guarded instanceof User) {
            return $guarded;
        }

        $default = Auth::user();

        return $default instanceof User ? $default : null;
    }
}
