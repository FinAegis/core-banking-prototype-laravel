<?php

namespace App\Http\Controllers;

use App\Services\Cgo\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class StripeWebhookController extends CashierController
{
    /**
     * Handle checkout session completed
     */
    protected function handleCheckoutSessionCompleted(array $payload)
    {
        $stripeService = new StripePaymentService();
        $stripeService->handleWebhook($payload);
        
        return $this->successMethod();
    }
    
    /**
     * Handle payment intent succeeded
     */
    protected function handlePaymentIntentSucceeded(array $payload)
    {
        $stripeService = new StripePaymentService();
        $stripeService->handleWebhook($payload);
        
        return $this->successMethod();
    }
    
    /**
     * Handle payment intent failed
     */
    protected function handlePaymentIntentPaymentFailed(array $payload)
    {
        $stripeService = new StripePaymentService();
        $stripeService->handleWebhook($payload);
        
        return $this->successMethod();
    }
}