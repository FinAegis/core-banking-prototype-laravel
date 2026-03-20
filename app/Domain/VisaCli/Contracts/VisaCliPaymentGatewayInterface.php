<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Contracts;

use App\Domain\FinancialInstitution\Models\PartnerInvoice;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentResult;

interface VisaCliPaymentGatewayInterface
{
    /**
     * Collect payment for a partner invoice.
     */
    public function collectPayment(PartnerInvoice $invoice, ?string $cardId = null): VisaCliPaymentResult;

    /**
     * Get payment status by reference.
     */
    public function getPaymentStatus(string $paymentReference): VisaCliPaymentResult;

    /**
     * Refund a payment by reference.
     */
    public function refundPayment(string $paymentReference, ?int $amountCents = null): VisaCliPaymentResult;
}
