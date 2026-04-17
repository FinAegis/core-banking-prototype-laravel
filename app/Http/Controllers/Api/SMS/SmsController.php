<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SMS;

use App\Domain\SMS\Services\SmsPricingService;
use App\Domain\SMS\Services\SmsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'SMS')]
class SmsController extends Controller
{
    public function __construct(
        private readonly SmsService $smsService,
        private readonly SmsPricingService $pricing,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/sms/send',
        operationId: 'smsSend',
        summary: 'Send an SMS message (payment-gated)',
        description: 'Send an SMS to any phone number via VertexSMS. Protected by MPP payment gate — the first request without payment returns 402 with available rails and pricing. After paying via USDC (x402), Stripe, or Lightning, resend with the payment proof in the Authorization header. Rate limited to 60 requests/minute, 1 SMS/second outbound.',
        tags: ['SMS'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['to', 'message'],
            properties: [
                new OA\Property(property: 'to', type: 'string', example: '+37069912345', description: 'E.164 phone number (international format with + prefix)'),
                new OA\Property(property: 'from', type: 'string', example: 'Zelta', description: 'Sender ID — alphanumeric, max 20 chars. Defaults to configured sender ID if omitted.'),
                new OA\Property(property: 'message', type: 'string', example: 'Hello from Zelta!', description: 'Message body, max 1600 chars. Long messages are automatically split into multiple parts and priced accordingly.'),
            ],
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'SMS sent successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'message_id', type: 'string', example: '1281532560', description: 'VertexSMS message ID — use with GET /v1/sms/status/{id}'),
                    new OA\Property(property: 'status', type: 'string', enum: ['sent'], example: 'sent'),
                    new OA\Property(property: 'parts', type: 'integer', example: 1, description: 'Number of SMS parts (long messages split automatically)'),
                    new OA\Property(property: 'destination', type: 'string', example: '+37069912345'),
                    new OA\Property(property: 'price_usdc', type: 'string', example: '48438', description: 'Charged amount in atomic USDC (6 decimals, e.g. 48438 = $0.048438)'),
                ]),
            ],
        ),
    )]
    #[OA\Response(
        response: 402,
        description: 'Payment required — MPP challenge with available rails and pricing',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'type', type: 'string', example: 'urn:ietf:rfc:9457'),
                new OA\Property(property: 'title', type: 'string', example: 'Payment Required'),
                new OA\Property(property: 'detail', type: 'string', example: 'This resource requires payment via the Machine Payment Protocol.'),
            ],
        ),
    )]
    #[OA\Response(response: 422, description: 'Validation error — invalid phone number format, missing message, or message exceeds 1600 chars')]
    #[OA\Response(response: 503, description: 'SMS service disabled — SMS_ENABLED is false')]
    public function send(Request $request): JsonResponse
    {
        if (! config('sms.enabled', false)) {
            return response()->json(['error' => 'SMS service is not enabled'], 503);
        }

        $request->validate([
            'to'      => ['required', 'string', 'regex:/^\+?[1-9]\d{4,18}$/'],
            'from'    => ['nullable', 'string', 'regex:/^[a-zA-Z0-9 ]{1,20}$/'],
            'message' => ['required', 'string', 'min:1', 'max:1600'],
        ]);

        $from = $request->input('from', config('sms.defaults.sender_id', 'Zelta'));

        // Extract payment metadata from MPP middleware (if present)
        /** @var array{rail?: string, payment_id?: string, receipt_id?: string} $paymentMeta */
        $paymentMeta = $request->attributes->get('mpp_payment', []);

        $result = $this->smsService->send(
            to: (string) $request->input('to'),
            from: (string) $from,
            message: (string) $request->input('message'),
            paymentMeta: is_array($paymentMeta) ? $paymentMeta : [],
        );

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/sms/rates',
        operationId: 'smsRates',
        summary: 'Get SMS rates for a country',
        description: 'Returns per-message pricing for a given ISO 3166-1 alpha-2 country code. Rates are sourced from VertexSMS in EUR and converted to atomic USDC with a configurable margin. Public endpoint — no authentication required.',
        tags: ['SMS'],
        parameters: [
            new OA\Parameter(name: 'country', in: 'query', required: true, schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 2), example: 'LT', description: 'ISO 3166-1 alpha-2 country code (e.g. LT, DE, US)'),
        ],
    )]
    #[OA\Response(
        response: 200,
        description: 'Rate found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'country', type: 'string', example: 'Lithuania', description: 'Country name'),
                    new OA\Property(property: 'country_code', type: 'string', example: 'LT', description: 'ISO 3166-1 alpha-2'),
                    new OA\Property(property: 'rate_eur', type: 'string', example: '0.0390', description: 'VertexSMS rate per SMS part in EUR'),
                    new OA\Property(property: 'rate_usdc', type: 'string', example: '48438', description: 'Per-part price in atomic USDC (6 decimals, includes margin)'),
                ]),
            ],
        ),
    )]
    #[OA\Response(response: 404, description: 'No rate found for country')]
    #[OA\Response(response: 422, description: 'Invalid country code — must be exactly 2 alphabetic characters')]
    public function rates(Request $request): JsonResponse
    {
        $request->validate([
            'country' => ['required', 'string', 'size:2', 'alpha'],
        ]);

        $countryCode = strtoupper((string) $request->input('country'));
        $rate = $this->pricing->getRateForDisplay($countryCode);

        if ($rate === null) {
            return response()->json([
                'data'    => null,
                'message' => 'No rate found for country: ' . $countryCode,
            ], 404);
        }

        return response()->json([
            'data' => $rate,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/sms/status/{messageId}',
        operationId: 'smsStatus',
        summary: 'Get message delivery status',
        description: 'Returns the current delivery status of a previously sent SMS. The message_id is the value returned from POST /v1/sms/send. Statuses progress: pending → sent → delivered (or failed). Delivery reports are received asynchronously from VertexSMS via webhook.',
        tags: ['SMS'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'messageId', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '1281532560', description: 'The message_id returned from POST /v1/sms/send'),
        ],
    )]
    #[OA\Response(
        response: 200,
        description: 'Message status',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'message_id', type: 'string', example: '1281532560'),
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'sent', 'delivered', 'failed'], example: 'delivered'),
                    new OA\Property(property: 'delivered_at', type: 'string', nullable: true, example: '2026-04-17T12:01:05+00:00', description: 'ISO 8601 timestamp when delivered, null if not yet delivered'),
                    new OA\Property(property: 'payment_status', type: 'string', nullable: true, enum: ['pending', 'settled'], example: 'settled', description: 'Payment settlement status'),
                ]),
            ],
        ),
    )]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
    #[OA\Response(response: 404, description: 'Message not found — no message with this ID exists')]
    public function status(string $messageId): JsonResponse
    {
        $result = $this->smsService->getStatus($messageId);

        if ($result === null) {
            return response()->json([
                'data'    => null,
                'message' => 'Message not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/sms/info',
        operationId: 'smsInfo',
        summary: 'Get SMS service info',
        description: 'Returns the active SMS provider, enabled/disabled status, test mode flag, and supported payment networks (CAIP-2 format). Public endpoint — no authentication required.',
        tags: ['SMS'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Service info',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'provider', type: 'string', example: 'vertexsms', description: 'Active SMS provider'),
                    new OA\Property(property: 'enabled', type: 'boolean', example: true, description: 'Whether SMS sending is enabled'),
                    new OA\Property(property: 'test_mode', type: 'boolean', example: false, description: 'When true, messages are accepted but not delivered'),
                    new OA\Property(property: 'networks', type: 'array', items: new OA\Items(type: 'string'), example: '["eip155:8453","eip155:1"]', description: 'Supported payment networks in CAIP-2 format (Base, Ethereum)'),
                ]),
            ],
        ),
    )]
    public function info(): JsonResponse
    {
        return response()->json([
            'data' => $this->smsService->getSupportedInfo(),
        ]);
    }
}
