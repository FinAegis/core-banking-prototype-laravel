<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Mail;

use App\Domain\Newsletter\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Double opt-in: the only message a new (pending) subscriber receives. It
 * carries a signed, expiring link to the confirmation route; the welcome
 * mail is sent after the link is opened.
 */
class SubscriberConfirmation extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Subscriber $subscriber,
        public string $confirmationUrl,
        public int $expiresInHours,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your subscription to ' . config('brand.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscriber.confirm',
            with: [
                'confirmationUrl' => $this->confirmationUrl,
                'expiresInHours'  => $this->expiresInHours,
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
