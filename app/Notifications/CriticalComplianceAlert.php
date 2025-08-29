<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Compliance\Models\ComplianceAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CriticalComplianceAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ComplianceAlert $alert
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
        // Critical alerts go to multiple channels
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->error()
            ->priority(1)
            ->subject('🚨 CRITICAL COMPLIANCE ALERT: ' . $this->alert->title)
            ->greeting('URGENT: Critical Compliance Alert')
            ->line('A critical compliance issue requires immediate attention.')
            ->line('Alert Type: ' . $this->alert->type)
            ->line('Severity: CRITICAL')
            ->line('Title: ' . $this->alert->title)
            ->line('Description: ' . $this->alert->description)
            ->line('Risk Score: ' . ($this->alert->risk_score ?? 'N/A'))
            ->action('View Alert Immediately', url('/compliance/alerts/' . $this->alert->id))
            ->line('⚠️ This is a critical alert that requires immediate action.')
            ->line('Please respond within the compliance SLA timeframe.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'alert_id'    => $this->alert->id,
            'type'        => $this->alert->type,
            'severity'    => 'CRITICAL',
            'title'       => $this->alert->title,
            'description' => $this->alert->description,
            'risk_score'  => $this->alert->risk_score,
            'created_at'  => $this->alert->created_at->toIso8601String(),
            'is_critical' => true,
        ];
    }
}
