<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Custodian\Models\CustodianWebhook;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Custodian Webhooks",
 *     description="Endpoints for receiving webhook notifications from custodian banks"
 * )
 */
class CustodianWebhookController extends Controller
{
    public function __construct()
    {
    }

    /**
     * Handle incoming webhook from Paysera.
     *
     * @OA\Post(
     *     path="/api/webhooks/custodian/paysera",
     *     operationId="payseraWebhook",
     *     tags={"Custodian Webhooks"},
     *     summary="Receive Paysera webhook",
     *     description="Endpoint for receiving webhook notifications from Paysera bank",
     *
     * @OA\RequestBody(
     *         required=true,
     *         description="Webhook payload from Paysera",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="event",     type="string", example="transaction.completed"),
     * @OA\Property(property="event_id",  type="string", example="evt_123456"),
     * @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Event-specific data"
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=202,
     *         description="Webhook accepted for processing",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="status",    type="string", example="accepted"),
     * @OA\Property(property="duplicate", type="boolean", example=false)
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=401,
     *         description="Invalid signature",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="error",     type="string", example="Invalid signature")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=400,
     *         description="Invalid payload"
     *     ),
     * @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function paysera(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'paysera');
    }

    /**
     * Handle incoming webhook from Santander.
     *
     * @OA\Post(
     *     path="/api/webhooks/custodian/santander",
     *     operationId="santanderWebhook",
     *     tags={"Custodian Webhooks"},
     *     summary="Receive Santander webhook",
     *     description="Endpoint for receiving webhook notifications from Santander bank",
     *
     * @OA\RequestBody(
     *         required=true,
     *         description="Webhook payload from Santander",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="event_type", type="string", example="payment.received"),
     * @OA\Property(property="id",         type="string", example="wh_987654"),
     * @OA\Property(
     *                 property="payload",
     *                 type="object",
     *                 description="Event-specific payload"
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=202,
     *         description="Webhook accepted for processing",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="status",     type="string", example="accepted")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=401,
     *         description="Invalid signature"
     *     ),
     * @OA\Response(
     *         response=400,
     *         description="Invalid payload"
     *     )
     * )
     */
    public function santander(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'santander');
    }

    /**
     * Handle incoming webhook from Mock Bank.
     *
     * @OA\Post(
     *     path="/api/webhooks/custodian/mock",
     *     operationId="mockBankWebhook",
     *     tags={"Custodian Webhooks"},
     *     summary="Receive Mock Bank webhook",
     *     description="Endpoint for receiving webhook notifications from Mock Bank (for testing)",
     *
     * @OA\RequestBody(
     *         required=true,
     *         description="Webhook payload from Mock Bank",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="type",       type="string", example="balance.updated"),
     * @OA\Property(property="id",         type="string", example="mock_123"),
     * @OA\Property(
     *                 property="data",
     *                 type="object",
     * @OA\Property(property="account_id", type="string", example="ACC123"),
     * @OA\Property(property="balance",    type="integer", example=150000),
     * @OA\Property(property="currency",   type="string", example="EUR")
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=202,
     *         description="Webhook accepted for processing"
     *     ),
     * @OA\Response(
     *         response=401,
     *         description="Invalid signature"
     *     ),
     * @OA\Response(
     *         response=400,
     *         description="Invalid payload"
     *     )
     * )
     */
    public function mock(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'mock');
    }

    /**
     * Common webhook handler.
     */
    private function handleWebhook(Request $request, string $custodianName): JsonResponse
    {
        $payload = $request->getContent();
        $headers = $request->headers->all();

        // Signature validation is now handled by middleware
        // Extract signature for logging purposes
        $signature = match ($custodianName) {
            'paysera'   => $request->header('X-Paysera-Signature', ''),
            'santander' => $request->header('X-Santander-Signature', ''),
            'mock'      => 'mock-signature',
            default     => '',
        };

        // Parse the payload
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error(
                'Invalid webhook payload',
                [
                    'custodian' => $custodianName,
                    'error'     => json_last_error_msg(),
                ]
            );

            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Extract event information
        $eventType = $this->extractEventType($custodianName, $data);
        $eventId = $this->extractEventId($custodianName, $data);

        // Store webhook for processing
        try {
            $webhook = CustodianWebhook::create(
                [
                    'custodian_name' => $custodianName,
                    'event_type'     => $eventType,
                    'event_id'       => $eventId,
                    'headers'        => $headers,
                    'payload'        => $data,
                    'signature'      => $signature,
                    'status'         => 'pending',
                ]
            );

            // Dispatch job to process webhook asynchronously
            dispatch(new \App\Jobs\ProcessCustodianWebhook($webhook->uuid));

            Log::info(
                'Webhook received and queued',
                [
                    'custodian'  => $custodianName,
                    'event_type' => $eventType,
                    'webhook_id' => $webhook->id,
                ]
            );

            return response()->json(['status' => 'accepted'], 202);
        } catch (\Illuminate\Database\QueryException $e) {
            // Check if it's a duplicate key violation
            if ($e->getCode() === '23000') {
                Log::info(
                    'Duplicate webhook received',
                    [
                        'custodian' => $custodianName,
                        'event_id'  => $eventId,
                    ]
                );

                // Return success to prevent webhook provider from retrying
                return response()->json(['status' => 'accepted', 'duplicate' => true], 202);
            }

            throw $e;
        } catch (Exception $e) {
            Log::error(
                'Failed to store webhook',
                [
                    'custodian' => $custodianName,
                    'error'     => $e->getMessage(),
                ]
            );

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Extract event type based on custodian format.
     */
    private function extractEventType(string $custodianName, array $data): string
    {
        return match ($custodianName) {
            'paysera'   => $data['event'] ?? 'unknown',
            'santander' => $data['event_type'] ?? 'unknown',
            'mock'      => $data['type'] ?? 'unknown',
            default     => 'unknown',
        };
    }

    /**
     * Extract event ID based on custodian format.
     */
    private function extractEventId(string $custodianName, array $data): ?string
    {
        return match ($custodianName) {
            'paysera'   => $data['event_id'] ?? null,
            'santander' => $data['id'] ?? null,
            'mock'      => $data['id'] ?? null,
            default     => null,
        };
    }
}
