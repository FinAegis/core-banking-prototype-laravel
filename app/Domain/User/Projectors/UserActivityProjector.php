<?php

declare(strict_types=1);

namespace App\Domain\User\Projectors;

use App\Domain\User\Events\UserActivityTracked;
use App\Models\UserActivity;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class UserActivityProjector extends Projector
{
    /**
     * Handle user activity tracked event.
     */
    public function onUserActivityTracked(UserActivityTracked $event): void
    {
        UserActivity::create([
            'user_id'    => $event->userId,
            'activity'   => $event->activity,
            'context'    => $event->context,
            'tracked_at' => $event->trackedAt,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
            'session_id' => $event->sessionId,
        ]);
    }
}
