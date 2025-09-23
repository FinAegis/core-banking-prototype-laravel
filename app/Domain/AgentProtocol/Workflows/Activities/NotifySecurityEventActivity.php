<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Workflow\Activity;

class NotifySecurityEventActivity extends Activity
{
    public function execute(
        string $transactionId,
        string $agentId,
        string $eventType,
        array $eventData = []
    ): array {
        try {
            // Log the security event
            Log::channel('security')->warning('Security event detected', [
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'event_type'     => $eventType,
                'event_data'     => $eventData,
                'timestamp'      => now()->toIso8601String(),
            ]);

            // Send notification based on event type
            $notificationSent = match ($eventType) {
                'rejected', 'security_failure' => $this->sendHighPriorityAlert($transactionId, $agentId, $eventData),
                'review_required', 'audit_alert' => $this->sendMediumPriorityAlert($transactionId, $agentId, $eventData),
                default => $this->sendLowPriorityNotification($transactionId, $agentId, $eventData),
            };

            // Store event in database (simplified)
            $this->storeSecurityEvent($transactionId, $agentId, $eventType, $eventData);

            return [
                'success'           => true,
                'notification_sent' => $notificationSent,
                'event_type'        => $eventType,
                'transaction_id'    => $transactionId,
                'agent_id'          => $agentId,
                'timestamp'         => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to notify security event', [
                'error'          => $e->getMessage(),
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'event_type'     => $eventType,
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    private function sendHighPriorityAlert(string $transactionId, string $agentId, array $eventData): bool
    {
        // In production, send actual email/SMS/push notification
        Log::alert('HIGH PRIORITY SECURITY ALERT', [
            'transaction_id' => $transactionId,
            'agent_id'       => $agentId,
            'data'           => $eventData,
        ]);

        // Simulate email sending
        // Mail::to(config('security.alert_email'))
        //     ->send(new SecurityAlertMail($transactionId, $agentId, $eventData));

        return true;
    }

    private function sendMediumPriorityAlert(string $transactionId, string $agentId, array $eventData): bool
    {
        Log::warning('MEDIUM PRIORITY SECURITY ALERT', [
            'transaction_id' => $transactionId,
            'agent_id'       => $agentId,
            'data'           => $eventData,
        ]);

        return true;
    }

    private function sendLowPriorityNotification(string $transactionId, string $agentId, array $eventData): bool
    {
        Log::info('Security notification', [
            'transaction_id' => $transactionId,
            'agent_id'       => $agentId,
            'data'           => $eventData,
        ]);

        return true;
    }

    private function storeSecurityEvent(
        string $transactionId,
        string $agentId,
        string $eventType,
        array $eventData
    ): void {
        // In production, store in database
        // DB::table('security_events')->insert([...]);
    }
}
