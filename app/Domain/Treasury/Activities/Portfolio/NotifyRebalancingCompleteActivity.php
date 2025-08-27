<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities\Portfolio;

use App\Domain\Treasury\Events\Portfolio\RebalancingCompleted;
use App\Domain\Treasury\Events\Portfolio\RebalancingFailed;
use Exception;
use Log;
use RuntimeException;
use Workflow\Activity;

class NotifyRebalancingCompleteActivity extends Activity
{
    public function execute(array $input): array
    {
        $portfolioId = $input['portfolio_id'];
        $rebalanceId = $input['rebalance_id'];
        $executionResults = $input['execution_results'];
        $originalPlan = $input['original_plan'];
        $status = $input['status'] ?? 'completed';

        try {
            $success = $executionResults['success'] ?? false;

            if ($success && $status === 'completed') {
                return $this->handleSuccessfulCompletion($portfolioId, $rebalanceId, $executionResults, $originalPlan);
            } else {
                return $this->handleFailure($portfolioId, $rebalanceId, $executionResults, $originalPlan, $status);
            }
        } catch (Exception $e) {
            Log::error('Failed to send rebalancing completion notifications', [
                'portfolio_id' => $portfolioId,
                'rebalance_id' => $rebalanceId,
                'error'        => $e->getMessage(),
            ]);

            throw new RuntimeException(
                "Failed to notify rebalancing completion for portfolio {$portfolioId}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Handle successful rebalancing completion.
     */
    private function handleSuccessfulCompletion(
        string $portfolioId,
        string $rebalanceId,
        array $executionResults,
        array $originalPlan
    ): array {
        // Dispatch success event
        event(new RebalancingCompleted(
            $portfolioId,
            $rebalanceId,
            $originalPlan['actions'] ?? [],
            $executionResults['execution_metrics'] ?? [],
            'system',
            [
                'completed_at'      => $executionResults['executed_at'] ?? now()->toISOString(),
                'actions_executed'  => $executionResults['actions_executed'] ?? 0,
                'total_cost'        => $executionResults['total_cost'] ?? 0,
                'estimated_benefit' => $executionResults['estimated_benefit'] ?? 0,
                'execution_time'    => $executionResults['execution_metrics']['execution_time_seconds'] ?? 0,
            ]
        ));

        // Send notifications to stakeholders
        $notifications = $this->sendSuccessNotifications($portfolioId, $rebalanceId, $executionResults, $originalPlan);

        Log::info('Rebalancing completion notifications sent', [
            'portfolio_id'       => $portfolioId,
            'rebalance_id'       => $rebalanceId,
            'notifications_sent' => count($notifications),
            'actions_executed'   => $executionResults['actions_executed'] ?? 0,
            'total_cost'         => $executionResults['total_cost'] ?? 0,
        ]);

        return [
            'success'           => true,
            'portfolio_id'      => $portfolioId,
            'rebalance_id'      => $rebalanceId,
            'notification_type' => 'success',
            'notifications'     => $notifications,
            'event_dispatched'  => 'RebalancingCompleted',
            'summary'           => [
                'status'           => 'completed',
                'actions_executed' => $executionResults['actions_executed'] ?? 0,
                'total_cost'       => $executionResults['total_cost'] ?? 0,
                'execution_time'   => $executionResults['execution_metrics']['execution_time_seconds'] ?? 0,
            ],
        ];
    }

    /**
     * Handle rebalancing failure or rejection.
     */
    private function handleFailure(
        string $portfolioId,
        string $rebalanceId,
        array $executionResults,
        array $originalPlan,
        string $status
    ): array {
        $reason = match ($status) {
            'failed'   => $executionResults['error'] ?? 'Execution failed',
            'rejected' => 'Approval rejected',
            'timeout'  => 'Approval timeout',
            default    => 'Unknown failure',
        };

        // Dispatch failure event
        event(new RebalancingFailed(
            $portfolioId,
            $rebalanceId,
            $reason,
            $originalPlan['actions'] ?? [],
            'system',
            [
                'failed_at'         => now()->toISOString(),
                'failure_reason'    => $reason,
                'status'            => $status,
                'partial_execution' => $executionResults['partial_execution'] ?? [],
                'rollback_needed'   => $executionResults['rollback_needed'] ?? false,
            ]
        ));

        // Send failure notifications to stakeholders
        $notifications = $this->sendFailureNotifications($portfolioId, $rebalanceId, $reason, $status, $originalPlan);

        Log::warning('Rebalancing failure notifications sent', [
            'portfolio_id'       => $portfolioId,
            'rebalance_id'       => $rebalanceId,
            'status'             => $status,
            'reason'             => $reason,
            'notifications_sent' => count($notifications),
        ]);

        return [
            'success'           => true, // Notification succeeded even though rebalancing failed
            'portfolio_id'      => $portfolioId,
            'rebalance_id'      => $rebalanceId,
            'notification_type' => 'failure',
            'notifications'     => $notifications,
            'event_dispatched'  => 'RebalancingFailed',
            'summary'           => [
                'status'          => $status,
                'reason'          => $reason,
                'rollback_needed' => $executionResults['rollback_needed'] ?? false,
            ],
        ];
    }

    /**
     * Send success notifications to relevant stakeholders.
     */
    private function sendSuccessNotifications(
        string $portfolioId,
        string $rebalanceId,
        array $executionResults,
        array $originalPlan
    ): array {
        $notifications = [];

        $notificationData = [
            'portfolio_id'      => $portfolioId,
            'rebalance_id'      => $rebalanceId,
            'status'            => 'completed',
            'actions_executed'  => $executionResults['actions_executed'] ?? 0,
            'total_cost'        => $executionResults['total_cost'] ?? 0,
            'execution_time'    => $executionResults['execution_metrics']['execution_time_seconds'] ?? 0,
            'estimated_benefit' => $executionResults['estimated_benefit'] ?? 0,
            'completed_at'      => $executionResults['executed_at'] ?? now()->toISOString(),
        ];

        // Determine notification recipients based on rebalancing characteristics
        $recipients = $this->determineSuccessRecipients($originalPlan);

        foreach ($recipients as $recipient) {
            try {
                $notification = $this->sendNotification($recipient, 'rebalancing_success', $notificationData);
                $notifications[] = $notification;
            } catch (Exception $e) {
                Log::warning('Failed to send success notification', [
                    'recipient'    => $recipient,
                    'portfolio_id' => $portfolioId,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return $notifications;
    }

    /**
     * Send failure notifications to relevant stakeholders.
     */
    private function sendFailureNotifications(
        string $portfolioId,
        string $rebalanceId,
        string $reason,
        string $status,
        array $originalPlan
    ): array {
        $notifications = [];

        $notificationData = [
            'portfolio_id'       => $portfolioId,
            'rebalance_id'       => $rebalanceId,
            'status'             => $status,
            'failure_reason'     => $reason,
            'planned_actions'    => count($originalPlan['actions'] ?? []),
            'planned_cost'       => $originalPlan['total_transaction_cost'] ?? 0,
            'failed_at'          => now()->toISOString(),
            'requires_attention' => true,
        ];

        // Failure notifications go to more stakeholders
        $recipients = $this->determineFailureRecipients($originalPlan, $status);

        foreach ($recipients as $recipient) {
            try {
                $notification = $this->sendNotification($recipient, 'rebalancing_failure', $notificationData);
                $notifications[] = $notification;
            } catch (Exception $e) {
                Log::warning('Failed to send failure notification', [
                    'recipient'    => $recipient,
                    'portfolio_id' => $portfolioId,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return $notifications;
    }

    /**
     * Determine who should receive success notifications.
     */
    private function determineSuccessRecipients(array $originalPlan): array
    {
        $recipients = ['portfolio_manager'];

        $totalCost = $originalPlan['total_transaction_cost'] ?? 0;

        // Include additional recipients for significant rebalancing
        if ($totalCost > 50000) {
            $recipients[] = 'risk_manager';
        }

        if ($totalCost > 100000) {
            $recipients[] = 'senior_management';
        }

        return array_unique($recipients);
    }

    /**
     * Determine who should receive failure notifications.
     */
    private function determineFailureRecipients(array $originalPlan, string $status): array
    {
        $recipients = ['portfolio_manager', 'risk_manager'];

        // Always include operations team for failures
        $recipients[] = 'operations_team';

        // Include senior management for high-value failures
        $totalCost = $originalPlan['total_transaction_cost'] ?? 0;
        if ($totalCost > 50000) {
            $recipients[] = 'senior_management';
        }

        // Include compliance for approval-related failures
        if ($status === 'rejected' || $status === 'timeout') {
            $recipients[] = 'compliance_officer';
        }

        return array_unique($recipients);
    }

    /**
     * Send a notification to a specific recipient.
     */
    private function sendNotification(string $recipient, string $type, array $data): array
    {
        // In a real implementation, this would:
        // 1. Send emails
        // 2. Update dashboards
        // 3. Send push notifications
        // 4. Create audit logs
        // 5. Trigger webhooks

        $notification = [
            'recipient' => $recipient,
            'type'      => $type,
            'sent_at'   => now()->toISOString(),
            'channel'   => 'email', // Could be email, sms, push, dashboard, etc.
            'success'   => true,
            'data'      => $data,
        ];

        // Simulate notification sending
        Log::info('Notification sent', [
            'recipient'    => $recipient,
            'type'         => $type,
            'portfolio_id' => $data['portfolio_id'] ?? null,
        ]);

        return $notification;
    }
}
