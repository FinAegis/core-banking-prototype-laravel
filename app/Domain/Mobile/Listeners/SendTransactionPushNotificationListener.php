<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Listeners;

use App\Domain\Account\Events\MoneyTransferred;
use App\Domain\Account\Models\Account;
use App\Domain\Mobile\Models\MobileNotificationPreference;
use App\Domain\Mobile\Services\NotificationPreferenceService;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Listens to money transfer events and sends push notifications.
 *
 * Optimized to minimize database queries by loading accounts and users in batch.
 */
class SendTransactionPushNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue connection for this listener.
     */
    public string $connection = 'redis';

    /**
     * The queue name for this listener.
     */
    public string $queue = 'mobile';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        private readonly PushNotificationService $pushService,
        private readonly NotificationPreferenceService $preferenceService,
    ) {
    }

    /**
     * Handle the MoneyTransferred event.
     */
    public function handle(MoneyTransferred $event): void
    {
        try {
            // Load both accounts in a single query to prevent N+1
            $accountUuids = [(string) $event->from, (string) $event->to];
            $accounts = Account::whereIn('uuid', $accountUuids)->get()->keyBy('uuid');

            $fromAccount = $accounts->get((string) $event->from);
            $toAccount = $accounts->get((string) $event->to);

            if ($fromAccount === null && $toAccount === null) {
                return;
            }

            // Load users in a single query
            $userUuids = array_filter([
                $fromAccount?->user_uuid,
                $toAccount?->user_uuid,
            ]);

            $users = User::whereIn('uuid', $userUuids)->get()->keyBy('uuid');

            $sender = $fromAccount !== null ? $users->get($fromAccount->user_uuid) : null;
            $recipient = $toAccount !== null ? $users->get($toAccount->user_uuid) : null;

            $amount = $this->formatAmount($event->money->getAmount());
            $currency = 'GCU'; // Default platform currency

            // Notify recipient
            if ($recipient !== null) {
                $this->notifyRecipient($recipient, $sender, $amount, $currency, $event);
            }

            // Notify sender
            if ($sender !== null) {
                $this->notifySender($sender, $recipient, $amount, $currency, $event);
            }
        } catch (Throwable $e) {
            Log::error('Failed to send transaction push notification', [
                'from_account' => (string) $event->from,
                'to_account'   => (string) $event->to,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify the recipient of a transfer.
     */
    private function notifyRecipient(
        User $recipient,
        ?User $sender,
        string $amount,
        string $currency,
        MoneyTransferred $event
    ): void {
        // Check if push notifications are enabled for this type
        if (! $this->preferenceService->isPushEnabled($recipient, MobileNotificationPreference::TYPE_TRANSACTION_RECEIVED)) {
            return;
        }

        $senderName = $sender !== null ? $sender->name : 'Unknown';

        $this->pushService->sendTransactionReceived(
            $recipient,
            $amount,
            $currency,
            $senderName
        );

        Log::info('Transaction received push notification sent', [
            'user_id'      => $recipient->id,
            'from_account' => (string) $event->from,
            'to_account'   => (string) $event->to,
            'amount'       => $amount,
            'currency'     => $currency,
        ]);
    }

    /**
     * Notify the sender of a completed transfer.
     */
    private function notifySender(
        User $sender,
        ?User $recipient,
        string $amount,
        string $currency,
        MoneyTransferred $event
    ): void {
        // Check if push notifications are enabled for this type
        if (! $this->preferenceService->isPushEnabled($sender, MobileNotificationPreference::TYPE_TRANSACTION_SENT)) {
            return;
        }

        $recipientName = $recipient !== null ? $recipient->name : 'Unknown';

        $this->pushService->sendTransactionSent(
            $sender,
            $amount,
            $currency,
            $recipientName
        );

        Log::info('Transaction sent push notification sent', [
            'user_id'      => $sender->id,
            'from_account' => (string) $event->from,
            'to_account'   => (string) $event->to,
            'amount'       => $amount,
            'currency'     => $currency,
        ]);
    }

    /**
     * Format the amount for display.
     *
     * Converts from minor units (cents) to major units.
     */
    private function formatAmount(int $amountInMinorUnits): string
    {
        return number_format($amountInMinorUnits / 100, 2);
    }

    /**
     * Handle a job failure.
     */
    public function failed(MoneyTransferred $event, Throwable $exception): void
    {
        Log::error('Transaction push notification listener failed permanently', [
            'from_account' => (string) $event->from,
            'to_account'   => (string) $event->to,
            'error'        => $exception->getMessage(),
        ]);
    }
}
