<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a new email address that a user entered on their profile. The
 * address replaces the old one only after the link in this email is opened
 * (see ConfirmEmailChangeController). The text is fixed: nothing the user
 * typed appears in it.
 */
class ConfirmEmailChange extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $confirmationUrl,
        public readonly int $validForMinutes,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(__('Confirm your new email address'))
            ->line(__('Someone asked to use this email address for an account.'))
            ->line(__('Open the link below to confirm the change. The link works for :minutes minutes.', ['minutes' => $this->validForMinutes]))
            ->action(__('Confirm email address'), $this->confirmationUrl)
            ->line(__('If you did not ask for this, ignore this email. Nothing will change.'));
    }
}
