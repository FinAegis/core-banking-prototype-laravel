<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\DataObjects\PaymentResult;
use App\Domain\AgentProtocol\Events\PaymentNotificationSent;
use App\Domain\AgentProtocol\Services\AgentNotificationService;
use Exception;
use stdClass;
use Workflow\Activity;

/**
 * Notifies involved agents about payment status.
 */
class NotifyAgentsActivity extends Activity
{
    /**
     * Execute agent notification.
     *
     * @param AgentPaymentRequest $request The payment request
     * @param PaymentResult $result The payment result
     * @return stdClass Notification result
     */
    public function execute(AgentPaymentRequest $request, PaymentResult $result): stdClass
    {
        $notificationResult = new stdClass();
        $notificationResult->success = false;
        $notificationResult->notificationsSent = 0;
        $notificationResult->failures = [];

        try {
            $notificationService = app(AgentNotificationService::class);

            // Prepare notification payload
            $payload = [
                'transaction_id' => $request->transactionId,
                'payment_id'     => $result->paymentId ?? $request->transactionId,
                'amount'         => $request->amount,
                'currency'       => $request->currency,
                'status'         => $result->status,
                'timestamp'      => $result->completedAt?->toIso8601String() ?? now()->toIso8601String(),
                'fees'           => $result->fees ?? 0.0,
                'escrow_id'      => $result->escrowId ?? null,
            ];

            // Notify sender
            try {
                $senderNotification = $notificationService->notify(
                    agentDid: $request->fromAgentDid,
                    type: 'payment_sent',
                    data: array_merge($payload, [
                        'recipient'     => $request->toAgentDid,
                        'direction'     => 'outgoing',
                        'balance_after' => $this->getAgentBalance($request->fromAgentDid),
                    ])
                );

                if ($senderNotification) {
                    $notificationResult->notificationsSent++;

                    // Record notification event
                    event(new PaymentNotificationSent(
                        agentDid: $request->fromAgentDid,
                        notificationType: 'payment_sent',
                        paymentId: $result->paymentId ?? $request->transactionId,
                        timestamp: now()
                    ));
                }
            } catch (Exception $e) {
                $notificationResult->failures[] = [
                    'agent' => $request->fromAgentDid,
                    'type'  => 'sender',
                    'error' => $e->getMessage(),
                ];
            }

            // Notify receiver
            try {
                $receiverNotification = $notificationService->notify(
                    agentDid: $request->toAgentDid,
                    type: 'payment_received',
                    data: array_merge($payload, [
                        'sender'        => $request->fromAgentDid,
                        'direction'     => 'incoming',
                        'balance_after' => $this->getAgentBalance($request->toAgentDid),
                    ])
                );

                if ($receiverNotification) {
                    $notificationResult->notificationsSent++;

                    // Record notification event
                    event(new PaymentNotificationSent(
                        agentDid: $request->toAgentDid,
                        notificationType: 'payment_received',
                        paymentId: $result->paymentId ?? $request->transactionId,
                        timestamp: now()
                    ));
                }
            } catch (Exception $e) {
                $notificationResult->failures[] = [
                    'agent' => $request->toAgentDid,
                    'type'  => 'receiver',
                    'error' => $e->getMessage(),
                ];
            }

            // Notify any watchers or subscribers
            if (isset($request->metadata['notify_agents'])) {
                $watchers = (array) $request->metadata['notify_agents'];
                foreach ($watchers as $watcherDid) {
                    try {
                        $watcherNotification = $notificationService->notify(
                            agentDid: $watcherDid,
                            type: 'payment_observed',
                            data: array_merge($payload, [
                                'sender'    => $request->fromAgentDid,
                                'recipient' => $request->toAgentDid,
                                'role'      => 'observer',
                            ])
                        );

                        if ($watcherNotification) {
                            $notificationResult->notificationsSent++;
                        }
                    } catch (Exception $e) {
                        $notificationResult->failures[] = [
                            'agent' => $watcherDid,
                            'type'  => 'watcher',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }

            // Handle escrow notifications if applicable
            if ($result->escrowId && $result->status === 'processing') {
                $this->sendEscrowNotifications($request, $result, $notificationService, $notificationResult);
            }

            $notificationResult->success = $notificationResult->notificationsSent > 0;
            $notificationResult->timestamp = now()->toIso8601String();

            if (! empty($notificationResult->failures)) {
                logger()->warning('Some payment notifications failed', [
                    'transaction_id' => $request->transactionId,
                    'failures'       => $notificationResult->failures,
                ]);
            }
        } catch (Exception $e) {
            $notificationResult->success = false;
            $notificationResult->errorMessage = $e->getMessage();

            logger()->error('Payment notification failed', [
                'transaction_id' => $request->transactionId,
                'error'          => $e->getMessage(),
            ]);
        }

        return $notificationResult;
    }

    /**
     * Send escrow-specific notifications.
     */
    private function sendEscrowNotifications(
        AgentPaymentRequest $request,
        PaymentResult $result,
        AgentNotificationService $notificationService,
        stdClass $notificationResult
    ): void {
        $escrowPayload = [
            'escrow_id'      => $result->escrowId,
            'transaction_id' => $request->transactionId,
            'amount'         => $request->amount,
            'conditions'     => $request->escrowConditions ?? [],
            'status'         => 'created',
        ];

        // Notify both parties about escrow creation
        try {
            $notificationService->notify(
                agentDid: $request->fromAgentDid,
                type: 'escrow_created',
                data: array_merge($escrowPayload, ['role' => 'sender'])
            );
            $notificationResult->notificationsSent++;
        } catch (Exception $e) {
            // Log but don't fail
        }

        try {
            $notificationService->notify(
                agentDid: $request->toAgentDid,
                type: 'escrow_created',
                data: array_merge($escrowPayload, ['role' => 'receiver'])
            );
            $notificationResult->notificationsSent++;
        } catch (Exception $e) {
            // Log but don't fail
        }
    }

    /**
     * Get agent's current balance.
     */
    private function getAgentBalance(string $agentDid): float
    {
        try {
            // For now, use the aggregate directly
            $wallet = \App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate::retrieve($agentDid);

            return $wallet->getBalance();
        } catch (Exception $e) {
            return 0.0;
        }
    }
}
