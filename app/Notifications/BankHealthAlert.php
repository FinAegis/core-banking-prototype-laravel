<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BankHealthAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string $custodian,
        public readonly string $previousStatus,
        public readonly string $newStatus,
        public readonly string $severity,
        public readonly array $healthData,
        public readonly \DateTimeInterface $timestamp
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->getEmailSubject();
        $color = $this->getAlertColor();
        
        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Bank Health Alert: {$this->custodian}")
            ->line("Status changed from **{$this->previousStatus}** to **{$this->newStatus}**")
            ->line("Time: {$this->timestamp->format('Y-m-d H:i:s')}");
            
        // Add health metrics
        if (isset($this->healthData['overall_failure_rate'])) {
            $mail->line("Failure Rate: {$this->healthData['overall_failure_rate']}%");
        }
        
        // Add recommendations
        if (!empty($this->healthData['recommendations'])) {
            $mail->line('**Recommendations:**');
            foreach ($this->healthData['recommendations'] as $recommendation) {
                $mail->line("• {$recommendation}");
            }
        }
        
        // Add action button based on severity
        if ($this->severity === 'critical') {
            $mail->action('View Bank Status', url('/admin'))
                ->error();
        } else {
            $mail->action('View Bank Status', url('/admin'));
        }
        
        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bank_health_alert',
            'custodian' => $this->custodian,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'severity' => $this->severity,
            'failure_rate' => $this->healthData['overall_failure_rate'] ?? null,
            'timestamp' => $this->timestamp->toIso8601String(),
        ];
    }
    
    /**
     * Get email subject based on severity
     */
    private function getEmailSubject(): string
    {
        return match ($this->severity) {
            'critical' => "[CRITICAL] Bank Connector {$this->custodian} is {$this->newStatus}",
            'warning' => "[WARNING] Bank Connector {$this->custodian} is {$this->newStatus}",
            default => "[INFO] Bank Connector {$this->custodian} status update",
        };
    }
    
    /**
     * Get alert color based on severity
     */
    private function getAlertColor(): string
    {
        return match ($this->severity) {
            'critical' => 'red',
            'warning' => 'yellow',
            default => 'blue',
        };
    }
}