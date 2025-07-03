<?php

namespace App\Mail;

use App\Models\CgoInvestment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CgoInvestmentReceived extends Mailable
{
    use Queueable, SerializesModels;

    public CgoInvestment $investment;

    /**
     * Create a new message instance.
     */
    public function __construct(CgoInvestment $investment)
    {
        $this->investment = $investment;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'CGO Investment Confirmation - FinAegis',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.cgo.investment-received',
            with: [
                'investment' => $this->investment,
                'amount' => number_format($this->investment->amount, 2),
                'tier' => ucfirst($this->investment->tier),
                'shares' => number_format($this->investment->shares_purchased, 4),
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