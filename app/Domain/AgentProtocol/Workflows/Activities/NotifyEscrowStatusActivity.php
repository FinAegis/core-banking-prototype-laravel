<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Events\EscrowStatusNotificationSent;
use App\Domain\AgentProtocol\Services\AgentNotificationService;
use Exception;
use stdClass;
use Workflow\Activity;

/**
 * Notifies agents about escrow status changes.
 */
class NotifyEscrowStatusActivity extends Activity
{
    /**
     * Execute escrow status notification.
     *
     * @param string $escrowId The escrow ID
     * @param string $status The escrow status
     * @param array $agentDids The agents to notify
     * @return stdClass Notification result
     */
    public function execute(string $escrowId, string $status, array $agentDids): stdClass
    {
        $result = new stdClass();
        $result->success = false;
        $result->notificationsSent = 0;
        $result->failures = [];

        try {
            $notificationService = app(AgentNotificationService::class);

            // Prepare base notification payload
            $basePayload = [
                'escrow_id' => $escrowId,
                'status'    => $status,
                'timestamp' => now()->toIso8601String(),
            ];

            // Status-specific payload additions
            $statusMessages = [
                'created'   => 'Escrow has been created and funded',
                'released'  => 'Escrow funds have been released',
                'returned'  => 'Escrow funds have been returned to sender',
                'expired'   => 'Escrow has expired',
                'cancelled' => 'Escrow has been cancelled',
                'disputed'  => 'Escrow is under dispute',
                'resolved'  => 'Escrow dispute has been resolved',
            ];

            $basePayload['message'] = $statusMessages[$status] ?? 'Escrow status changed to ' . $status;

            // Send notifications to each agent
            foreach ($agentDids as $agentDid) {
                try {
                    $agentPayload = $basePayload;

                    // Add agent-specific context
                    $agentPayload['agent_role'] = $this->determineAgentRole($agentDid, $escrowId);

                    // Determine notification type based on status
                    $notificationType = 'escrow_' . $status;

                    $sent = $notificationService->notify(
                        agentDid: $agentDid,
                        type: $notificationType,
                        data: $agentPayload
                    );

                    if ($sent) {
                        $result->notificationsSent++;

                        // Record notification event
                        event(new EscrowStatusNotificationSent(
                            escrowId: $escrowId,
                            agentDid: $agentDid,
                            status: $status,
                            timestamp: now()
                        ));
                    }
                } catch (Exception $e) {
                    $result->failures[] = [
                        'agent' => $agentDid,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Handle special cases based on status
            $this->handleSpecialNotifications($escrowId, $status, $notificationService, $result);

            $result->success = $result->notificationsSent > 0;

            if (! empty($result->failures)) {
                logger()->warning('Some escrow notifications failed', [
                    'escrow_id' => $escrowId,
                    'status'    => $status,
                    'failures'  => $result->failures,
                ]);
            }
        } catch (Exception $e) {
            $result->success = false;
            $result->errorMessage = $e->getMessage();

            logger()->error('Escrow status notification failed', [
                'escrow_id' => $escrowId,
                'status'    => $status,
                'error'     => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Determine the agent's role in the escrow.
     */
    private function determineAgentRole(string $agentDid, string $escrowId): string
    {
        try {
            $escrow = \App\Domain\AgentProtocol\Aggregates\EscrowAggregate::retrieve($escrowId);

            if ($escrow->getSenderAgentId() === $agentDid) {
                return 'sender';
            }
            if ($escrow->getReceiverAgentId() === $agentDid) {
                return 'receiver';
            }

            return 'observer';
        } catch (Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Handle special notifications based on status.
     */
    private function handleSpecialNotifications(
        string $escrowId,
        string $status,
        AgentNotificationService $notificationService,
        stdClass $result
    ): void {
        switch ($status) {
            case 'expired':
                // Notify system administrators about expired escrows
                try {
                    $notificationService->notifySystemAdmins(
                        type: 'escrow_expired',
                        data: [
                            'escrow_id' => $escrowId,
                            'status'    => $status,
                            'timestamp' => now()->toIso8601String(),
                            'action'    => 'manual_review_required',
                        ]
                    );
                    $result->notificationsSent++;
                } catch (Exception $e) {
                    // Log but don't fail
                }
                break;

            case 'disputed':
                // Notify arbiters about disputes
                try {
                    $arbiters = $this->getArbiters();
                    foreach ($arbiters as $arbiterDid) {
                        $notificationService->notify(
                            agentDid: $arbiterDid,
                            type: 'escrow_dispute_assignment',
                            data: [
                                'escrow_id' => $escrowId,
                                'status'    => $status,
                                'timestamp' => now()->toIso8601String(),
                                'action'    => 'review_required',
                            ]
                        );
                        $result->notificationsSent++;
                    }
                } catch (Exception $e) {
                    // Log but don't fail
                }
                break;
        }
    }

    /**
     * Get arbiter DIDs for dispute resolution.
     */
    private function getArbiters(): array
    {
        // In production, this would fetch from a service or database
        return [
            'did:agent:finaegis:arbiter-1',
            'did:agent:finaegis:arbiter-2',
        ];
    }
}
