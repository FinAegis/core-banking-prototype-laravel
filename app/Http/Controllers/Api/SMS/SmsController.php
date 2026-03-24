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
        summary: 'Send an SMS message',
        description: 'Send an SMS message to a phone number. Protected by MPP payment gate — requires payment via x402, Stripe, or other configured rail.',
        tags: ['SMS'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['to', 'message'],
            properties: [
                new OA\Property(property: 'to', type: 'string', example: '+37069912345', description: 'E.164 phone number'),
                new OA\Property(property: 'from', type: 'string', example: 'Zelta', description: 'Sender ID (alphanumeric, max 20 chars)'),
                new OA\Property(property: 'message', type: 'string', example: 'Hello from Zelta!', description: 'Message body (max 1600 chars)'),
            ],
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'SMS sent successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'message_id', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'sent']),
                    new OA\Property(property: 'price_usdc', type: 'string'),
                    new OA\Property(property: 'parts', type: 'integer'),
                ]),
            ],
        ),
    )]
    #[OA\Response(response: 402, description: 'Payment required — MPP challenge issued')]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 503, description: 'SMS service disabled')]
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
            'data' => $result,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/sms/rates',
        operationId: 'smsRates',
        summary: 'Get SMS rates for a country',
        description: 'Returns per-message USDC pricing for a given ISO 3166-1 alpha-2 country code.',
        tags: ['SMS'],
        parameters: [
            new OA\Parameter(name: 'country', in: 'query', required: true, schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 2), example: 'LT'),
        ],
    )]
    #[OA\Response(
        response: 200,
        description: 'Rate found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'country_code', type: 'string'),
                    new OA\Property(property: 'price_usdc', type: 'string'),
                    new OA\Property(property: 'currency', type: 'string', example: 'USDC'),
                ]),
            ],
        ),
    )]
    #[OA\Response(response: 404, description: 'No rate found for country')]
    #[OA\Response(response: 422, description: 'Invalid country code')]
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
        description: 'Returns the current delivery status of a previously sent SMS message. Requires authentication.',
        tags: ['SMS'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'messageId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
    )]
    #[OA\Response(
        response: 200,
        description: 'Message status',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'message_id', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'sent', 'delivered', 'failed']),
                    new OA\Property(property: 'delivered_at', type: 'string', nullable: true),
                ]),
            ],
        ),
    )]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
    #[OA\Response(response: 404, description: 'Message not found')]
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
            'data' => $result,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/sms/info',
        operationId: 'smsInfo',
        summary: 'Get SMS service info',
        description: 'Returns provider information, enabled status, test mode flag, and supported networks.',
        tags: ['SMS'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Service info',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'provider', type: 'string'),
                    new OA\Property(property: 'enabled', type: 'boolean'),
                    new OA\Property(property: 'test_mode', type: 'boolean'),
                    new OA\Property(property: 'networks', type: 'array', items: new OA\Items(type: 'string')),
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
