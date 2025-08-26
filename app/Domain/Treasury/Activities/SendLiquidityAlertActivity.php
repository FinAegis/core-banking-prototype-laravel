<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities;

use Workflow\Activity\ActivityInterface;
use Workflow\Activity\ActivityMethod;

/**
 * Activity for sending liquidity alerts.
 */
#[ActivityInterface]
class SendLiquidityAlertActivity
{
    #[ActivityMethod]
    public function execute(string $treasuryId, array $alerts): array
    {
        $notifications = [];

        foreach ($alerts as $alert) {
            // In production, would send actual notifications
            // via email, SMS, Slack, etc.
            $notifications[] = [
                'type'        => 'liquidity_alert',
                'treasury_id' => $treasuryId,
                'alert'       => $alert,
                'sent_at'     => now()->toIso8601String(),
                'channels'    => ['email', 'dashboard'],
            ];
        }

        return [
            'notifications_sent' => count($notifications),
            'details'            => $notifications,
        ];
    }
}
