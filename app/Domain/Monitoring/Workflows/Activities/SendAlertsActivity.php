<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Workflows\Activities;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendAlertsActivity
{
    public function execute(array $data): array
    {
        $alerts = $data['alerts'] ?? [];
        $sent = [];
        $failed = [];

        foreach ($alerts as $alert) {
            try {
                $this->sendAlert($alert);
                $sent[] = $alert['name'];
            } catch (\Exception $e) {
                Log::error('Failed to send alert', [
                    'alert' => $alert,
                    'error' => $e->getMessage(),
                ]);
                $failed[] = $alert['name'];
            }
        }

        return [
            'sent'   => $sent,
            'failed' => $failed,
            'total'  => count($alerts),
        ];
    }

    private function sendAlert(array $alert): void
    {
        $severity = $alert['severity'] ?? 'info';
        $message = $alert['message'] ?? 'Unknown alert';

        // Log the alert
        match ($severity) {
            'critical' => Log::critical($message, $alert),
            'error'    => Log::error($message, $alert),
            'warning'  => Log::warning($message, $alert),
            default    => Log::info($message, $alert),
        };

        // Store alert in database for dashboard
        \DB::table('monitoring_alerts')->insert([
            'name'         => $alert['name'],
            'severity'     => $severity,
            'message'      => $message,
            'context'      => json_encode($alert),
            'acknowledged' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Send notifications for critical alerts
        if ($severity === 'critical') {
            $this->sendCriticalAlertNotifications($alert);
        }
    }

    private function sendCriticalAlertNotifications(array $alert): void
    {
        // Get admin users
        $admins = \App\Models\User::where('is_admin', true)->get();

        if ($admins->isEmpty()) {
            return;
        }

        // Send notifications (in production, this would send actual emails/SMS)
        foreach ($admins as $admin) {
            try {
                // For demo purposes, just log the notification
                Log::channel('monitoring')->info('Critical alert notification', [
                    'user'    => $admin->email,
                    'alert'   => $alert['name'],
                    'message' => $alert['message'],
                ]);

                // In production, uncomment this:
                // Notification::send($admin, new CriticalAlertNotification($alert));
            } catch (\Exception $e) {
                Log::error('Failed to send notification to admin', [
                    'user'  => $admin->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
