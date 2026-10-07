<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Relayer;

use App\Domain\Relayer\Services\GasStationService;
use App\Domain\Relayer\Services\SponsorshipRequestValidator;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

/**
 * GraphQL counterpart of REST POST /api/v1/relayer/sponsor. Both share
 * {@see SponsorshipRequestValidator}: unavailable unless
 * RELAYER_GENERIC_SPONSOR_ENABLED is on, sender bound to the caller,
 * USDC/USDT transfer calldata only, networks from WALLET_SEND_EVM_NETWORKS.
 * A rejected request surfaces as a client-safe GraphQL error.
 */
final class SponsorTransactionMutation
{
    public function __construct(
        private readonly GasStationService $gasStationService,
        private readonly SponsorshipRequestValidator $sponsorshipValidator,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): string
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $network = $this->sponsorshipValidator->validate(
            user: $user,
            sender: (string) $args['sender'],
            callData: (string) $args['data'],
            networkKey: (string) $args['network'],
        );

        $result = $this->gasStationService->sponsorTransaction(
            userAddress: (string) $args['sender'],
            callData: (string) $args['data'],
            signature: (string) ($args['value'] ?? '0x'),
            network: $network,
        );

        return json_encode($result) ?: '{}';
    }
}
