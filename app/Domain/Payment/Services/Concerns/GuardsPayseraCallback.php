<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services\Concerns;

use App\Domain\Account\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Shared rules for crediting a Paysera order: the order must belong to the
 * user in user_id (the signed-in user on the browser return, the user stored
 * with the order on Paysera's server notification) and target an account
 * that user owns, the amount and currency come from the stored order (a
 * callback value that differs is refused), and a completed order is not
 * credited again (the DepositReplayGuard claim then makes this race-free).
 */
trait GuardsPayseraCallback
{
    /**
     * Why the callback must not credit the order, or null when it may.
     *
     * @param  array<string, mixed>  $orderData
     * @param  array<string, mixed>  $callbackData
     */
    protected function payseraCallbackRejection(string $orderId, array $orderData, array $callbackData): ?string
    {
        $callbackUserId = (int) ($callbackData['user_id'] ?? 0);
        $orderUserId = (int) ($orderData['user_id'] ?? 0);
        $user = $callbackUserId > 0 ? User::query()->find($callbackUserId) : null;

        if (
            ! $user instanceof User
            || $orderUserId !== $callbackUserId
            || Account::findOwnedBy((string) ($orderData['account_uuid'] ?? ''), $user) === null
        ) {
            Log::warning('Paysera callback refused: order does not belong to the authenticated user', [
                'order_id' => $orderId,
                'user_id'  => $callbackUserId,
            ]);

            return 'Order not found';
        }

        if (
            (isset($callbackData['amount']) && (int) $callbackData['amount'] !== (int) ($orderData['amount'] ?? 0))
            || (isset($callbackData['currency']) && strtoupper((string) $callbackData['currency']) !== strtoupper((string) ($orderData['currency'] ?? '')))
        ) {
            Log::warning('Paysera callback refused: amount or currency differs from the order', [
                'order_id' => $orderId,
            ]);

            return 'Payment details do not match the order';
        }

        if (($orderData['status'] ?? null) === 'completed') {
            return 'Deposit already processed';
        }

        return null;
    }
}
