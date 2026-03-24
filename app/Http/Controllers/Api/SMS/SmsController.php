<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SMS;

use App\Domain\SMS\Services\SmsPricingService;
use App\Domain\SMS\Services\SmsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function __construct(
        private readonly SmsService $smsService,
        private readonly SmsPricingService $pricing,
    ) {
    }

    /**
     * Send an SMS message.
     *
     * This endpoint is protected by MppPaymentGateMiddleware.
     * Payment metadata is attached to the request by the middleware.
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'to'      => 'required|string|min:5|max:20',
            'from'    => 'nullable|string|max:20',
            'message' => 'required|string|min:1|max:1600',
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

    /**
     * Get SMS rates for a country.
     */
    public function rates(Request $request): JsonResponse
    {
        $request->validate([
            'country' => 'required|string|size:2',
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

    /**
     * Get message delivery status.
     */
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

    /**
     * Get SMS service info.
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'data' => $this->smsService->getSupportedInfo(),
        ]);
    }
}
