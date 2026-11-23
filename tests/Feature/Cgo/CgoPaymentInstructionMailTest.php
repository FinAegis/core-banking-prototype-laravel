<?php

declare(strict_types=1);

use App\Domain\Cgo\Mail\CgoBankTransferInstructions;
use App\Domain\Cgo\Mail\CgoCryptoPaymentInstructions;
use App\Domain\Cgo\Models\CgoInvestment;

// Both mailables pointed at Markdown views that did not exist, so sending
// (or resending) payment instructions failed with "View not found".

/**
 * @param array<model-property<CgoInvestment>, mixed> $attributes
 */
function cgoInstructionInvestment(array $attributes): CgoInvestment
{
    return (new CgoInvestment())->forceFill(array_merge([
        'uuid'     => 'a0d5c7c4-2f4e-4f0e-9a51-5d0d6f3b8e21',
        'amount'   => 5000,
        'currency' => 'USD',
        'tier'     => 'silver',
    ], $attributes));
}

it('renders the bank transfer instructions with the transfer reference', function (): void {
    config(['cgo.bank_details.bank_name' => 'Instruction Test Bank', 'cgo.bank_details.iban' => 'LT601010012345678901']);

    $investment = cgoInstructionInvestment([
        'payment_method'          => 'bank_transfer',
        'bank_transfer_reference' => 'CGO-REF-123',
    ]);

    $html = (new CgoBankTransferInstructions($investment))->render();

    // The amount is a decimal:2 string; number_format() rejected it under strict types.
    expect($html)->toContain('5,000.00')
        ->and($html)->toContain('Instruction Test Bank')
        ->and($html)->toContain('LT601010012345678901')
        ->and($html)->toContain('CGO-REF-123');
});

it('renders the crypto payment instructions with the stored address', function (): void {
    $investment = cgoInstructionInvestment([
        'payment_method' => 'crypto',
        'crypto_address' => 'bc1qinstructiontestaddress',
    ]);

    $html = (new CgoCryptoPaymentInstructions($investment))->render();

    expect($html)->toContain('bc1qinstructiontestaddress');
});

it('tells the investor to contact support when no crypto address is stored', function (): void {
    $investment = cgoInstructionInvestment([
        'payment_method' => 'crypto',
        'crypto_address' => null,
    ]);

    $html = (new CgoCryptoPaymentInstructions($investment))->render();

    expect($html)->toContain('contact our support team');
});
