<?php

namespace App\Http\Controllers;

use App\Services\Cgo\CoinbaseCommerceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CoinbaseWebhookController extends Controller
{
    protected CoinbaseCommerceService $coinbaseService;

    public function __construct(CoinbaseCommerceService $coinbaseService)
    {
        $this->coinbaseService = $coinbaseService;
    }

    /**
     * Handle Coinbase Commerce webhook
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-CC-Webhook-Signature');

        if (!$signature) {
            Log::warning('Coinbase webhook received without signature');
            return response()->json(['error' => 'Missing signature'], 400);
        }

        // Verify webhook signature
        if (!$this->coinbaseService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Coinbase webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);

        if (!$event) {
            Log::error('Invalid Coinbase webhook payload', ['payload' => $payload]);
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        try {
            // Process the webhook event
            $this->coinbaseService->processWebhookEvent($event['event'] ?? []);
            
            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            Log::error('Error processing Coinbase webhook', [
                'error' => $e->getMessage(),
                'event' => $event,
            ]);
            
            // Return 200 to prevent retries for processing errors
            return response()->json(['error' => 'Processing error'], 200);
        }
    }
}