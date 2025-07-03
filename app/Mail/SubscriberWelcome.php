<?php

namespace App\Mail;

use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriberWelcome extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Subscriber $subscriber
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $sourceText = match($this->subscriber->source) {
            Subscriber::SOURCE_BLOG => 'FinAegis Blog',
            Subscriber::SOURCE_CGO => 'CGO Early Access',
            Subscriber::SOURCE_INVESTMENT => 'Investment Platform',
            Subscriber::SOURCE_FOOTER => 'Newsletter',
            Subscriber::SOURCE_CONTACT => 'Contact Form',
            Subscriber::SOURCE_PARTNER => 'Partner Program',
            default => 'FinAegis Platform',
        };
        
        return new Envelope(
            subject: "Welcome to {$sourceText}!",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscriber.welcome',
            with: [
                'unsubscribeUrl' => route('subscriber.unsubscribe', [
                    'email' => encrypt($this->subscriber->email),
                ]),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
