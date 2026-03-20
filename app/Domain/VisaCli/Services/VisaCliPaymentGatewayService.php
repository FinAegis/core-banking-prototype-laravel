<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Services;

use App\Domain\FinancialInstitution\Models\PartnerInvoice;
use App\Domain\VisaCli\Contracts\VisaCliClientInterface;
use App\Domain\VisaCli\Contracts\VisaCliPaymentGatewayInterface;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentResult;
use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;
use App\Domain\VisaCli\Exceptions\VisaCliPaymentException;
use App\Domain\VisaCli\Models\VisaCliPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Payment gateway service for collecting partner invoice payments via Visa CLI.
 */
class VisaCliPaymentGatewayService implements VisaCliPaymentGatewayInterface
{
    public function __construct(
        private readonly VisaCliClientInterface $client,
    ) {
    }

    public function collectPayment(PartnerInvoice $invoice, ?string $cardId = null): VisaCliPaymentResult
    {
        if (! $invoice->canBePaid()) {
            throw new VisaCliPaymentException(
                "Invoice {$invoice->invoice_number} cannot be paid (status: {$invoice->status})."
            );
        }

        $amountCents = (int) round((float) $invoice->total_amount_usd * 100);
        $paymentUrl = config('app.url') . '/api/partner/v1/billing/invoices/' . $invoice->id;

        return DB::transaction(function () use ($invoice, $cardId, $amountCents, $paymentUrl): VisaCliPaymentResult {
            // Create payment record
            $payment = VisaCliPayment::create([
                'agent_id'        => 'invoice-gateway',
                'invoice_id'      => $invoice->id,
                'url'             => $paymentUrl,
                'amount_cents'    => $amountCents,
                'currency'        => 'USD',
                'status'          => VisaCliPaymentStatus::PROCESSING,
                'card_identifier' => $cardId,
                'metadata'        => [
                    'invoice_number' => $invoice->invoice_number,
                    'partner_id'     => $invoice->partner_id,
                ],
            ]);

            try {
                $result = $this->client->pay($paymentUrl, $amountCents, $cardId);

                $payment->markCompleted($result->paymentReference);

                // Mark the invoice as paid
                $invoice->markAsPaid('visa_cli', $result->paymentReference);

                Log::info('Visa CLI invoice payment collected', [
                    'invoice_number'    => $invoice->invoice_number,
                    'payment_reference' => $result->paymentReference,
                    'amount_cents'      => $amountCents,
                ]);

                return $result;
            } catch (Throwable $e) {
                $payment->markFailed($e->getMessage());

                Log::error('Visa CLI invoice payment failed', [
                    'invoice_number' => $invoice->invoice_number,
                    'error'          => $e->getMessage(),
                ]);

                throw new VisaCliPaymentException(
                    "Failed to collect payment for invoice {$invoice->invoice_number}: {$e->getMessage()}",
                    0,
                    $e,
                );
            }
        });
    }

    public function getPaymentStatus(string $paymentReference): VisaCliPaymentResult
    {
        $payment = VisaCliPayment::where('payment_reference', $paymentReference)->first();

        if ($payment === null) {
            throw new VisaCliPaymentException("Payment not found: {$paymentReference}");
        }

        $status = $payment->status instanceof VisaCliPaymentStatus
            ? $payment->status
            : VisaCliPaymentStatus::from($payment->status);

        return new VisaCliPaymentResult(
            paymentReference: (string) $payment->payment_reference,
            status: $status,
            amountCents: $payment->amount_cents,
            currency: $payment->currency,
            url: $payment->url,
            metadata: $payment->metadata ?? [],
        );
    }

    public function refundPayment(string $paymentReference, ?int $amountCents = null): VisaCliPaymentResult
    {
        $payment = VisaCliPayment::where('payment_reference', $paymentReference)->first();

        if ($payment === null) {
            throw new VisaCliPaymentException("Payment not found: {$paymentReference}");
        }

        $currentStatus = $payment->status instanceof VisaCliPaymentStatus
            ? $payment->status
            : VisaCliPaymentStatus::from($payment->status);

        if ($currentStatus !== VisaCliPaymentStatus::COMPLETED) {
            throw new VisaCliPaymentException("Cannot refund payment with status: {$currentStatus->value}");
        }

        $payment->update([
            'status'   => VisaCliPaymentStatus::REFUNDED,
            'metadata' => array_merge($payment->metadata ?? [], [
                'refunded_at'   => now()->toIso8601String(),
                'refund_amount' => $amountCents ?? $payment->amount_cents,
            ]),
        ]);

        Log::info('Visa CLI payment refunded', [
            'payment_reference' => $paymentReference,
            'amount_cents'      => $amountCents ?? $payment->amount_cents,
        ]);

        return new VisaCliPaymentResult(
            paymentReference: $paymentReference,
            status: VisaCliPaymentStatus::REFUNDED,
            amountCents: $amountCents ?? $payment->amount_cents,
            currency: $payment->currency,
            url: $payment->url,
            metadata: $payment->metadata ?? [],
        );
    }
}
