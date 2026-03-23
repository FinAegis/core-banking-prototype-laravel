<?php

declare(strict_types=1);

namespace App\Domain\User\Mail;

use App\Domain\User\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $acceptUrl;

    public string $brandName;

    public string $inviterName;

    public function __construct(
        public readonly UserInvitation $invitation,
        string $inviterName,
    ) {
        $this->brandName = (string) config('brand.name', 'Zelta');
        $this->inviterName = $inviterName;
        $this->acceptUrl = config('app.url') . '/invitation/accept?token=' . $invitation->token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->brandName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user-invitation',
        );
    }
}
