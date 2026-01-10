<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when an agent's reputation changes significantly.
 */
class AgentReputationChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param array<string, mixed> $notificationData
     */
    public function __construct(
        public array $notificationData
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = ['database'];

        // Add email for significant changes
        if (($this->notificationData['priority'] ?? 'normal') !== 'normal') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $reputation = $this->notificationData['reputation'] ?? [];
        $agent = $this->notificationData['agent'] ?? [];
        $priority = $this->notificationData['priority'] ?? 'normal';
        $message = $this->notificationData['message'] ?? 'Your agent reputation has changed.';

        $mailMessage = new MailMessage();

        // Set priority styling - error() for critical/high priority
        if ($priority === 'critical' || $priority === 'high') {
            $mailMessage->error();
        }

        $direction = $reputation['direction'] ?? 'changed';
        $emoji = $direction === 'increased' ? '📈' : '📉';

        return $mailMessage
            ->subject($emoji . ' Agent Reputation ' . ucfirst($direction))
            ->greeting('Hello!')
            ->line($message)
            ->line('')
            ->line('**Agent Details:**')
            ->line('- Agent: ' . ($agent['display_name'] ?? $agent['did'] ?? 'Unknown'))
            ->line('- Current Score: ' . ($reputation['current_score'] ?? 'N/A'))
            ->line('- Trust Level: ' . ucfirst($reputation['trust_level'] ?? 'unknown'))
            ->line('- Change: ' . ($reputation['change'] ?? 0) . ' points')
            ->line('')
            ->action('View Agent Dashboard', url('/admin/agent-protocol'))
            ->line('Thank you for using FinAegis!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $reputation = $this->notificationData['reputation'] ?? [];
        $agent = $this->notificationData['agent'] ?? [];
        $event = $this->notificationData['event'] ?? [];

        return [
            'type'          => 'agent_reputation_changed',
            'priority'      => $this->notificationData['priority'] ?? 'normal',
            'agent_did'     => $agent['did'] ?? null,
            'agent_name'    => $agent['display_name'] ?? null,
            'current_score' => $reputation['current_score'] ?? null,
            'trust_level'   => $reputation['trust_level'] ?? null,
            'score_change'  => $reputation['change'] ?? 0,
            'direction'     => $reputation['direction'] ?? 'changed',
            'event_type'    => $event['type'] ?? null,
            'message'       => $this->notificationData['message'] ?? null,
            'actions'       => $this->notificationData['actions'] ?? [],
            'timestamp'     => $event['timestamp'] ?? now()->toIso8601String(),
        ];
    }
}
