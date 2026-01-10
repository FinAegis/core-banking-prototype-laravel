<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\DataObjects\ReputationScore;
use App\Domain\AgentProtocol\Models\AgentIdentity;
use App\Domain\AgentProtocol\Services\AgentNotificationService;
use App\Models\User;
use App\Notifications\AgentReputationChanged;
use Exception;
use Illuminate\Support\Facades\Log;
use Workflow\Activity;

/**
 * Activity that sends notifications when an agent's reputation changes significantly.
 */
class NotifyReputationChangeActivity extends Activity
{
    // Notification thresholds
    private const SIGNIFICANT_CHANGE_THRESHOLD = 5.0;

    private const CRITICAL_CHANGE_THRESHOLD = 15.0;

    public function __construct(
        private readonly ?AgentNotificationService $notificationService = null
    ) {
    }

    public function execute(
        string $agentId,
        ReputationScore $currentScore,
        float $scoreChange,
        string $eventType,
        array $eventData = []
    ): array {
        $result = [
            'agent_id'           => $agentId,
            'notifications_sent' => [],
            'channels_used'      => [],
        ];

        // Get agent details
        $agent = AgentIdentity::where('did', $agentId)->first();
        if (! $agent) {
            Log::warning('Agent not found for notification', ['agent_id' => $agentId]);

            return $result;
        }

        // Determine notification priority based on change magnitude
        $priority = $this->determinePriority($scoreChange);
        $notificationType = $this->determineNotificationType($scoreChange, $eventType);

        // Build notification content
        $notification = $this->buildNotificationContent(
            $agent,
            $currentScore,
            $scoreChange,
            $eventType,
            $priority
        );

        // Send to agent's registered notification endpoints
        $result['notifications_sent'][] = $this->notifyAgent($agent, $notification);

        // Send to linked user if exists
        if (isset($agent->metadata['linked_user_id'])) {
            /** @var User|null $user */
            $user = User::find($agent->metadata['linked_user_id']);
            if ($user instanceof User) {
                $result['notifications_sent'][] = $this->notifyUser($user, $notification);
            }
        }

        // Send webhook if configured
        if (isset($agent->metadata['webhook_url'])) {
            $result['notifications_sent'][] = $this->sendWebhook(
                $agent->metadata['webhook_url'],
                $notification
            );
        }

        // For critical changes, notify platform administrators
        if ($priority === 'critical') {
            $result['notifications_sent'][] = $this->notifyAdministrators($agent, $notification);
        }

        $result['channels_used'] = array_unique(array_column($result['notifications_sent'], 'channel'));

        Log::info('Reputation change notifications sent', [
            'agent_id'            => $agentId,
            'score_change'        => $scoreChange,
            'notifications_count' => count($result['notifications_sent']),
        ]);

        return $result;
    }

    private function determinePriority(float $scoreChange): string
    {
        $absChange = abs($scoreChange);

        if ($absChange >= self::CRITICAL_CHANGE_THRESHOLD) {
            return 'critical';
        }

        if ($absChange >= self::SIGNIFICANT_CHANGE_THRESHOLD) {
            return 'high';
        }

        return 'normal';
    }

    private function determineNotificationType(float $scoreChange, string $eventType): string
    {
        if ($scoreChange > 0) {
            return match ($eventType) {
                'transaction_completed' => 'positive_transaction_feedback',
                'dispute_resolved'      => 'dispute_resolved_favorably',
                default                 => 'reputation_increased',
            };
        }

        return match ($eventType) {
            'transaction_failed' => 'negative_transaction_feedback',
            'dispute_raised'     => 'dispute_impact',
            'fraud_detected'     => 'fraud_penalty',
            default              => 'reputation_decreased',
        };
    }

    private function buildNotificationContent(
        AgentIdentity $agent,
        ReputationScore $currentScore,
        float $scoreChange,
        string $eventType,
        string $priority
    ): array {
        $direction = $scoreChange > 0 ? 'increased' : 'decreased';
        $absChange = abs($scoreChange);

        return [
            'type'     => 'reputation_change',
            'priority' => $priority,
            'agent'    => [
                'did'          => $agent->did,
                'display_name' => $agent->display_name,
            ],
            'reputation' => [
                'current_score' => $currentScore->score,
                'trust_level'   => $currentScore->trustLevel,
                'change'        => $scoreChange,
                'direction'     => $direction,
            ],
            'event' => [
                'type'      => $eventType,
                'timestamp' => now()->toIso8601String(),
            ],
            'message' => $this->generateMessage($direction, $absChange, $currentScore->trustLevel),
            'actions' => $this->suggestedActions($currentScore->score, $scoreChange),
        ];
    }

    private function generateMessage(string $direction, float $change, string $trustLevel): string
    {
        return sprintf(
            'Your agent reputation has %s by %.1f points. Current trust level: %s',
            $direction,
            $change,
            ucfirst($trustLevel)
        );
    }

    private function suggestedActions(float $score, float $change): array
    {
        $actions = [];

        if ($score < 40) {
            $actions[] = [
                'action'      => 'improve_reputation',
                'description' => 'Complete successful transactions to improve your reputation',
            ];
        }

        if ($change < -10) {
            $actions[] = [
                'action'      => 'review_activity',
                'description' => 'Review recent activity that may have caused this decline',
            ];
        }

        if ($score >= 80) {
            $actions[] = [
                'action'      => 'maintain_status',
                'description' => 'Continue maintaining high-quality service to keep your trusted status',
            ];
        }

        return $actions;
    }

    private function notifyAgent(AgentIdentity $agent, array $notification): array
    {
        // Use the notification service if available
        if ($this->notificationService) {
            $sent = $this->notificationService->notify(
                $agent->did ?? $agent->agent_id,
                'reputation_change',
                $notification
            );

            if ($sent) {
                return [
                    'channel'   => 'agent_notification_service',
                    'status'    => 'sent',
                    'recipient' => $agent->did ?? $agent->agent_id,
                ];
            }
        }

        // Store notification for later retrieval
        return [
            'channel'   => 'stored_notification',
            'status'    => 'stored',
            'recipient' => $agent->did ?? $agent->agent_id,
        ];
    }

    private function notifyUser(User $user, array $notification): array
    {
        try {
            $user->notify(new AgentReputationChanged($notification));

            Log::info('User notification sent', [
                'user_id'      => $user->id,
                'notification' => $notification['type'] ?? 'reputation_change',
                'priority'     => $notification['priority'] ?? 'normal',
            ]);

            return [
                'channel'   => 'user_notification',
                'status'    => 'sent',
                'recipient' => $user->email,
            ];
        } catch (Exception $e) {
            Log::error('Failed to send user notification', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return [
                'channel'   => 'user_notification',
                'status'    => 'failed',
                'recipient' => $user->email,
                'error'     => $e->getMessage(),
            ];
        }
    }

    private function sendWebhook(string $url, array $notification): array
    {
        try {
            // Send webhook notification
            /** @var \Illuminate\Http\Client\Response $response */
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders([
                    'Content-Type'    => 'application/json',
                    'X-Webhook-Event' => 'reputation.changed',
                ])
                ->post($url, $notification);

            return [
                'channel'     => 'webhook',
                'status'      => $response->successful() ? 'sent' : 'failed',
                'recipient'   => $url,
                'http_status' => $response->status(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to send webhook', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'channel'   => 'webhook',
                'status'    => 'failed',
                'recipient' => $url,
                'error'     => $e->getMessage(),
            ];
        }
    }

    private function notifyAdministrators(AgentIdentity $agent, array $notification): array
    {
        // Notify platform administrators about critical reputation changes
        Log::critical('Critical agent reputation change', [
            'agent_id'     => $agent->did,
            'notification' => $notification,
        ]);

        return [
            'channel'   => 'admin_alert',
            'status'    => 'logged',
            'recipient' => 'administrators',
        ];
    }
}
