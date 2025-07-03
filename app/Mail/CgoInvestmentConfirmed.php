<?php

namespace App\Mail;

use App\Models\CgoInvestment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CgoInvestmentConfirmed extends Mailable
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
            subject: 'Investment Confirmed - Certificate #' . $this->investment->certificate_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.cgo.investment-confirmed',
            with: [
                'investment' => $this->investment,
                'amount' => number_format($this->investment->amount, 2),
                'tier' => ucfirst($this->investment->tier),
                'shares' => number_format($this->investment->shares_purchased, 4),
                'certificateNumber' => $this->investment->certificate_number,
                'ownershipPercentage' => number_format($this->investment->ownership_percentage, 6),
                'certificateUrl' => route('cgo.certificate', ['uuid' => $this->investment->uuid]),
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