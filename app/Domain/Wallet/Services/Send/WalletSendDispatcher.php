<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use App\Domain\Wallet\Models\WalletSendRecord;
use App\Models\User;

/**
 * Routes a wallet send to the per-chain dispatcher.
 *
 * The chain branch is decided by the `network` value mobile sends:
 *   SOLANA → SolanaSendDispatcher (USDC, USDT)
 *   polygon | base | arbitrum | ethereum → EvmSendDispatcher (USDC, USDT, WETH, WBTC)
 *
 * Idempotency, persistence, and error reporting live inside each per-chain
 * dispatcher; this class only routes.
 */
class WalletSendDispatcher
{
    public function __construct(
        private readonly SolanaSendDispatcher $solana,
        private readonly EvmSendDispatcher $evm,
    ) {
    }

    public function dispatch(
        User $user,
        string $recipientAddress,
        string $assetSymbol,
        string $networkKey,
        string $amountMajor,
        ?string $idempotencyKey = null,
        ?string $quoteId = null,
    ): WalletSendRecord {
        if ($this->isSolana($networkKey)) {
            return $this->solana->dispatch(
                user: $user,
                recipientAddressBase58: $recipientAddress,
                assetSymbol: $assetSymbol,
                amountMajor: $amountMajor,
                idempotencyKey: $idempotencyKey,
                quoteId: $quoteId,
            );
        }

        return $this->evm->dispatch(
            user: $user,
            recipientAddress: $recipientAddress,
            assetSymbol: $assetSymbol,
            networkKey: strtolower($networkKey),
            amountMajor: $amountMajor,
            idempotencyKey: $idempotencyKey,
            quoteId: $quoteId,
        );
    }

    private function isSolana(string $networkKey): bool
    {
        return strtoupper($networkKey) === 'SOLANA';
    }
}
