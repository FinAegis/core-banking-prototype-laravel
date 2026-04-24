<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Support;

use App\Domain\AccountProvisioning\Services\AccountFlagsService;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * Cancels outbound notifications for users with an active
 * suppress_notifications flag. Returning false from handle() instructs
 * Laravel's NotificationSender to skip delivery on the current channel.
 */
class SuppressNotificationsListener
{
    public function __construct(private readonly AccountFlagsService $flags)
    {
    }

    public function handle(NotificationSending $event): bool
    {
        if (! $event->notifiable instanceof User) {
            return true;
        }

        return ! $this->flags->shouldSuppressNotifications($event->notifiable);
    }
}
