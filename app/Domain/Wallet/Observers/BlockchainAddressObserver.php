<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Observers;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Services\HeliusWebhookSyncService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Observes BlockchainAddress model events to sync Solana
 * addresses with Helius webhook monitoring.
 *
 * Queued via afterCommit to avoid blocking user registration
 * and to ensure the address is persisted before sync.
 */
class BlockchainAddressObserver
{
    public function __construct(
        private readonly HeliusWebhookSyncService $heliusSync,
    ) {
    }

    public function created(BlockchainAddress $address): void
    {
        if ($address->chain !== 'solana' || ! $address->is_active) {
            return;
        }

        dispatch(function () use ($address): void {
            try {
                $this->heliusSync->addAddress($address->address);
            } catch (Throwable $e) {
                Log::error('Solana webhook: Failed to sync new Solana address', [
                    'address' => $address->address,
                    'error'   => $e->getMessage(),
                ]);
            }
        })->afterCommit();
    }

    public function deleted(BlockchainAddress $address): void
    {
        if ($address->chain !== 'solana') {
            return;
        }

        dispatch(function () use ($address): void {
            try {
                $this->heliusSync->removeAddress($address->address);
            } catch (Throwable $e) {
                Log::error('Solana webhook: Failed to remove Solana address', [
                    'address' => $address->address,
                    'error'   => $e->getMessage(),
                ]);
            }
        })->afterCommit();
    }
}
