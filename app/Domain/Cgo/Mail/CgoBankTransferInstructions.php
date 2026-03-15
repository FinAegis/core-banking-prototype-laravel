<?php

declare(strict_types=1);

namespace App\Domain\Cgo\Mail;

use App\Domain\Cgo\Models\CgoInvestment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CgoBankTransferInstructions extends Mailable
{
    use Queueable;
    use SerializesModels;

    public CgoInvestment $investment;

    public function __construct(CgoInvestment $investment)
    {
        $this->investment = $investment;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'CGO Bank Transfer Instructions - ' . config('brand.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.cgo.bank-transfer-instructions',
            with: [
                'investment' => $this->investment,
                'amount'     => number_format($this->investment->amount, 2),
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
