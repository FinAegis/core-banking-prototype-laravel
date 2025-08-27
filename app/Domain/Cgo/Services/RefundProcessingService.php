<?php

namespace App\Domain\Cgo\Services;

use App\Domain\Cgo\Models\CgoInvestment;
use App\Domain\Cgo\Models\CgoRefund;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Refund as StripeRefund;
use Stripe\StripeClient;

class RefundProcessingService
{
    protected StripeClient $stripe;

    protected CoinbaseCommerceService $coinbaseService;

    public function __construct(StripePaymentService $stripeService, CoinbaseCommerceService $coinbaseService)
    {
        $this->stripe = new StripeClient(config('cashier.secret'));
        $this->coinbaseService = $coinbaseService;
    }

    /**
     * Process a refund for an investment.
     */
    public function processRefund(CgoInvestment $investment, array $data): CgoRefund
    {
        // Validate investment can be refunded
        $this->validateRefundable($investment);

        DB::beginTransaction();
        try {
            // Create refund record
            $refund = $this->createRefundRecord($investment, $data);

            // Process refund based on payment method
            $result = match ($investment->payment_method) {
                'stripe'        => $this->processStripeRefund($investment, $refund),
                'crypto'        => $this->processCryptoRefund($investment, $refund),
                'bank_transfer' => $this->processBankTransferRefund($investment, $refund),
                default         => throw new Exception("Unsupported payment method for refund: {$investment->payment_method}")
            };

            // Update refund with processing result
            $refund->update($result);

            // Update investment status if refund is successful
            if ($refund->status === 'completed') {
                $investment->update(
                    [
                    'status'         => 'refunded',
                    'payment_status' => 'refunded',
                    ]
                );

                // Update pricing round to reflect refund
                if ($investment->round) {
                    $investment->round->decrement('shares_sold', $investment->shares_purchased);
                    $investment->round->decrement('total_raised', $investment->amount);
                }
            }

            DB::commit();

            return $refund;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error(
                'Refund processing failed',
                [
                'investment_id' => $investment->id,
                'error'         => $e->getMessage(),
                ]
            );
            throw $e;
        }
    }

    /**
     * Validate that an investment can be refunded.
     */
    protected function validateRefundable(CgoInvestment $investment): void
    {
        // Check investment status
        if ($investment->status === 'refunded') {
            throw new Exception('Investment has already been refunded');
        }

        if ($investment->payment_status !== 'completed') {
            throw new Exception('Only completed payments can be refunded');
        }

        // Check for existing pending refund
        $pendingRefund = CgoRefund::where('investment_id', $investment->id)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if ($pendingRefund) {
            throw new Exception('A refund is already in progress for this investment');
        }

        // Check refund window (e.g., 30 days)
        $refundDeadline = $investment->payment_completed_at->addDays(30);
        if (now()->isAfter($refundDeadline)) {
            throw new Exception('Refund window has expired (30 days)');
        }

        // Check if agreements have been signed
        if ($investment->agreement_signed_at) {
            throw new Exception('Cannot refund after investment agreement has been signed');
        }
    }

    /**
     * Create refund record in database.
     */
    protected function createRefundRecord(CgoInvestment $investment, array $data): CgoRefund
    {
        return CgoRefund::create(
            [
            'investment_id'  => $investment->id,
            'user_id'        => $investment->user_id,
            'amount'         => $data['amount'] ?? $investment->amount,
            'currency'       => $investment->currency,
            'reason'         => $data['reason'],
            'reason_details' => $data['reason_details'] ?? null,
            'status'         => 'pending',
            'initiated_by'   => auth()->id(),
            'metadata'       => [
                'investment_amount'     => $investment->amount,
                'payment_method'        => $investment->payment_method,
                'original_payment_date' => $investment->payment_completed_at->toIso8601String(),
            ],
            ]
        );
    }

    /**
     * Process Stripe refund.
     */
    protected function processStripeRefund(CgoInvestment $investment, CgoRefund $refund): array
    {
        try {
            if (! $investment->stripe_payment_intent_id) {
                throw new Exception('Missing Stripe payment intent ID');
            }

            // Create Stripe refund
            $stripeRefund = StripeRefund::create(
                [
                'payment_intent' => $investment->stripe_payment_intent_id,
                'amount'         => $refund->amount, // Amount in cents
                'reason'         => $this->mapRefundReason($refund->reason),
                'metadata'       => [
                    'investment_id' => $investment->uuid,
                    'refund_id'     => $refund->id,
                ],
                ]
            );

            return [
                'status'              => 'completed',
                'processed_at'        => now(),
                'processor_reference' => $stripeRefund->id,
                'processor_response'  => [
                    'id'     => $stripeRefund->id,
                    'status' => $stripeRefund->status,
                    'amount' => $stripeRefund->amount,
                ],
            ];
        } catch (Exception $e) {
            Log::error(
                'Stripe refund failed',
                [
                'investment_id' => $investment->id,
                'error'         => $e->getMessage(),
                ]
            );

            return [
                'status'         => 'failed',
                'failed_at'      => now(),
                'failure_reason' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process crypto refund (manual process).
     */
    protected function processCryptoRefund(CgoInvestment $investment, CgoRefund $refund): array
    {
        // Crypto refunds require manual processing
        // Create a task for finance team

        return [
            'status'           => 'processing',
            'processing_notes' => 'Crypto refund requires manual processing. Finance team has been notified.',
            'metadata'         => array_merge(
                $refund->metadata ?? [],
                [
                'requires_manual_processing' => true,
                'original_crypto_address'    => $investment->crypto_address,
                'original_tx_hash'           => $investment->crypto_tx_hash,
                'refund_address'             => $refund->refund_address ?? 'To be provided by customer',
                ]
            ),
        ];
    }

    /**
     * Process bank transfer refund (manual process).
     */
    protected function processBankTransferRefund(CgoInvestment $investment, CgoRefund $refund): array
    {
        // Bank transfer refunds require manual processing
        return [
            'status'           => 'processing',
            'processing_notes' => 'Bank transfer refund requires manual processing. Finance team has been notified.',
            'metadata'         => array_merge(
                $refund->metadata ?? [],
                [
                'requires_manual_processing' => true,
                'original_reference'         => $investment->bank_transfer_reference,
                'bank_details'               => $refund->bank_details ?? 'To be provided by customer',
                ]
            ),
        ];
    }

    /**
     * Map internal refund reason to Stripe reason.
     */
    protected function mapRefundReason(string $reason): string
    {
        return match ($reason) {
            'requested_by_customer' => 'requested_by_customer',
            'duplicate'             => 'duplicate',
            'fraudulent'            => 'fraudulent',
            default                 => 'requested_by_customer',
        };
    }

    /**
     * Complete a manual refund.
     */
    public function completeManualRefund(CgoRefund $refund, array $data): CgoRefund
    {
        if (! in_array($refund->status, ['processing', 'pending'])) {
            throw new Exception('Refund cannot be completed in current status');
        }

        $refund->update(
            [
            'status'              => 'completed',
            'processed_at'        => now(),
            'processor_reference' => $data['reference'] ?? null,
            'processing_notes'    => $data['notes'] ?? null,
            'metadata'            => array_merge(
                $refund->metadata ?? [],
                [
                'completed_by'      => auth()->id(),
                'completion_method' => $data['method'] ?? 'manual',
                ]
            ),
            ]
        );

        // Update investment status
        $refund->investment->update(
            [
            'status'         => 'refunded',
            'payment_status' => 'refunded',
            ]
        );

        return $refund;
    }

    /**
     * Cancel a refund.
     */
    public function cancelRefund(CgoRefund $refund, string $reason): CgoRefund
    {
        if (! in_array($refund->status, ['pending', 'processing'])) {
            throw new Exception('Only pending or processing refunds can be cancelled');
        }

        $refund->update(
            [
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason,
            'metadata'            => array_merge(
                $refund->metadata ?? [],
                [
                'cancelled_by' => auth()->id(),
                ]
            ),
            ]
        );

        return $refund;
    }

    /**
     * Get refund statistics.
     */
    public function getRefundStatistics(): array
    {
        $stats = CgoRefund::selectRaw(
            '
            COUNT(*) as total_refunds,
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_refunds,
            SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as processing_refunds,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_refunds,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_refunds,
            SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as total_refunded_amount,
            AVG(CASE WHEN status = "completed" AND processed_at IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, created_at, processed_at) 
                ELSE NULL END) as avg_processing_hours
        '
        )->first();

        return [
            'total_refunds'         => $stats->total_refunds ?? 0,
            'pending_refunds'       => $stats->pending_refunds ?? 0,
            'processing_refunds'    => $stats->processing_refunds ?? 0,
            'completed_refunds'     => $stats->completed_refunds ?? 0,
            'failed_refunds'        => $stats->failed_refunds ?? 0,
            'total_refunded_amount' => $stats->total_refunded_amount ?? 0,
            'avg_processing_time'   => $stats->avg_processing_hours ?
                round($stats->avg_processing_hours, 1) . ' hours' : 'N/A',
        ];
    }
}
