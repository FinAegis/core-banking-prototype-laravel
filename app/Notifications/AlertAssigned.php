<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Compliance\Models\ComplianceAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertAssigned extends Notification implements ShouldQueue
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
            ->subject('Compliance Alert Assigned: ' . $this->alert->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A compliance alert has been assigned to you.')
            ->line('Alert Type: ' . $this->alert->type)
            ->line('Severity: ' . $this->alert->severity)
            ->line('Title: ' . $this->alert->title)
            ->line('Description: ' . $this->alert->description)
            ->action('View Alert', url('/compliance/alerts/' . $this->alert->id))
            ->line('Please review and take appropriate action as soon as possible.');
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
            'severity'    => $this->alert->severity,
            'title'       => $this->alert->title,
            'description' => $this->alert->description,
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
