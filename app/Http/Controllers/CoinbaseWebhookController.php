<?php

namespace App\Http\Controllers;

use App\Domain\Cgo\Services\CoinbaseCommerceService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Webhooks",
 *     description="Payment processor webhook endpoints"
 * )
 */
class CoinbaseWebhookController extends Controller
{
    protected CoinbaseCommerceService $coinbaseService;

    public function __construct(CoinbaseCommerceService $coinbaseService)
    {
        $this->coinbaseService = $coinbaseService;
    }

    /**
     * @OA\Post(
     *     path="/api/webhooks/coinbase-commerce",
     *     operationId="webhooksHandleWebhook",
     *     tags={"Webhooks"},
     *     summary="Handle Coinbase Commerce webhook",
     *     description="Processes incoming Coinbase Commerce payment webhooks",
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function handleWebhook(Request $request)
    {
        // Signature validation is now handled by middleware
        $payload = $request->getContent();
        $signature = $request->header('X-CC-Webhook-Signature');

        $event = json_decode($payload, true);

        if (! $event) {
            Log::error('Invalid Coinbase webhook payload', ['payload' => $payload]);

            return response()->json(['error' => 'Invalid payload'], 400);
        }

        try {
            // Process the webhook event
            $this->coinbaseService->processWebhookEvent($event['event'] ?? []);

            return response()->json(['success' => true], 200);
        } catch (Exception $e) {
            Log::error(
                'Error processing Coinbase webhook',
                [
                    'error' => $e->getMessage(),
                    'event' => $event,
                ]
            );

            // Return 200 to prevent retries for processing errors
            return response()->json(['error' => 'Processing error'], 200);
        }
    }
}
