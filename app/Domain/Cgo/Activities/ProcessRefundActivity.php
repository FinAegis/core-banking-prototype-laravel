<?php

namespace App\Domain\Cgo\Activities;

use App\Domain\Cgo\Aggregates\RefundAggregate;
use App\Models\CgoRefund;
use App\Services\Cgo\StripePaymentService;
use App\Services\Cgo\CoinbaseCommerceService;
use Workflow\Activity;

class ProcessRefundActivity extends Activity
{
    public function __construct(
        private StripePaymentService $stripeService,
        private CoinbaseCommerceService $coinbaseService
    ) {}
    
    public function execute(array $input): array
    {
        $refund = CgoRefund::with('investment')->findOrFail($input['refund_id']);
        $investment = $refund->investment;
        
        // Process refund based on original payment method
        $processorRefundId = null;
        $processorResponse = [];
        
        if ($investment->payment_method === 'stripe') {
            // Process Stripe refund
            $result = $this->stripeService->refundPayment(
                $investment->stripe_payment_intent_id,
                $refund->amount
            );
            $processorRefundId = $result['id'];
            $processorResponse = $result;
        } elseif ($investment->payment_method === 'coinbase_commerce') {
            // For crypto, we can't automatically refund
            // This would typically require manual processing or a different flow
            $processorRefundId = 'manual_crypto_refund_' . uniqid();
            $processorResponse = [
                'type' => 'manual_crypto_refund',
                'address' => $refund->refund_address,
                'amount' => $refund->amount,
                'currency' => $refund->currency
            ];
        } elseif ($investment->payment_method === 'bank_transfer') {
            // For bank transfers, this would integrate with banking APIs
            $processorRefundId = 'bank_refund_' . uniqid();
            $processorResponse = [
                'type' => 'bank_transfer_refund',
                'bank_details' => $refund->bank_details,
                'amount' => $refund->amount,
                'currency' => $refund->currency
            ];
        }
        
        // Update aggregate with processing details
        RefundAggregate::retrieve($input['refund_id'])
            ->process(
                paymentProcessor: $investment->payment_method,
                processorRefundId: $processorRefundId,
                status: 'processing',
                processorResponse: $processorResponse
            )
            ->persist();
        
        return [
            'refund_id' => $input['refund_id'],
            'processor_refund_id' => $processorRefundId,
            'amount_refunded' => $refund->amount,
            'status' => 'processing'
        ];
    }
}