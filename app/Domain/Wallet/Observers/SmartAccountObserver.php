<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Observers;

use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Services\AlchemyWebhookManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Observes SmartAccount model events to sync EVM addresses
 * with Alchemy Notify webhook monitoring.
 *
 * Best-effort: webhook failures are logged but never block the
 * user flow. Uses dispatch()->afterCommit() so the address is
 * persisted before the Alchemy API call fires.
 */
class SmartAccountObserver
{
    public function __construct(
        private readonly AlchemyWebhookManager $webhookManager,
    ) {
    }

    public function created(SmartAccount $account): void
    {
        dispatch(function () use ($account): void {
            try {
                $this->webhookManager->addAddress(
                    $account->account_address,
                    $account->network,
                );
            } catch (Throwable $e) {
                Log::error('EVM webhook: Failed to register address', [
                    'address' => $account->account_address,
                    'network' => $account->network,
                    'error'   => $e->getMessage(),
                ]);
            }
        })->afterCommit();
    }

    public function deleted(SmartAccount $account): void
    {
        dispatch(function () use ($account): void {
            try {
                $this->webhookManager->removeAddress(
                    $account->account_address,
                    $account->network,
                );
            } catch (Throwable $e) {
                Log::error('EVM webhook: Failed to unregister address', [
                    'address' => $account->account_address,
                    'network' => $account->network,
                    'error'   => $e->getMessage(),
                ]);
            }
        })->afterCommit();
    }
}
