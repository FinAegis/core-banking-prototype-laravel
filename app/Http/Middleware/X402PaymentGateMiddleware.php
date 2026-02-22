<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\X402\DataObjects\MonetizedRouteConfig;
use App\Domain\X402\Events\X402PaymentRequested;
use App\Domain\X402\Exceptions\X402SettlementException;
use App\Domain\X402\Services\X402HeaderCodecService;
use App\Domain\X402\Services\X402PaymentVerificationService;
use App\Domain\X402\Services\X402PricingService;
use App\Domain\X402\Services\X402SettlementService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * HTTP 402 Payment Required middleware.
 *
 * Intercepts requests to monetized endpoints and enforces the x402
 * payment protocol. If no valid payment is attached, returns 402 with
 * payment requirements. If a valid payment is present, verifies and
 * settles it before allowing the request through.
 */
class X402PaymentGateMiddleware
{
    public function __construct(
        private readonly X402PricingService $pricing,
        private readonly X402HeaderCodecService $codec,
        private readonly X402PaymentVerificationService $verification,
        private readonly X402SettlementService $settlement,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if x402 is globally enabled
        if (! config('x402.enabled', false)) {
            return $next($request);
        }

        // Look up monetization config for this route
        $routeConfig = $this->pricing->getRouteConfig($request);

        if ($routeConfig === null) {
            return $next($request);
        }

        // Check for payment signature header (case-insensitive per RFC 7230)
        $paymentHeader = $request->header('PAYMENT-SIGNATURE');

        if ($paymentHeader === null || $paymentHeader === '') {
            return $this->requirePayment($request, $routeConfig);
        }

        // Decode and verify the payment
        try {
            $payload = $this->codec->decodePaymentPayload($paymentHeader);
        } catch (Throwable $e) {
            Log::warning('x402: Invalid payment payload', [
                'error' => $e->getMessage(),
                'path'  => $request->path(),
            ]);

            return $this->requirePayment($request, $routeConfig, 'The payment signature header could not be decoded. Ensure it is a valid base64-encoded x402 payment payload.');
        }

        // Verify the payment with the facilitator
        $verifyResult = $this->verification->verify($payload, $routeConfig);

        if (! $verifyResult->isValid) {
            Log::warning('x402: Payment verification failed', [
                'reason'  => $verifyResult->invalidReason,
                'message' => $verifyResult->invalidMessage,
                'path'    => $request->path(),
            ]);

            return $this->requirePayment($request, $routeConfig, $verifyResult->invalidMessage);
        }

        // Settle the payment
        try {
            $settleResponse = $this->settlement->settle($payload, $routeConfig);
        } catch (X402SettlementException $e) {
            Log::error('x402: Settlement exception', [
                'error' => $e->getMessage(),
                'path'  => $request->path(),
            ]);

            return $this->requirePayment($request, $routeConfig, 'Settlement failed. The on-chain transfer could not be completed. Retry with a fresh authorization.');
        }

        if (! $settleResponse->success) {
            Log::error('x402: Payment settlement failed', [
                'reason'  => $settleResponse->errorReason,
                'message' => $settleResponse->errorMessage,
                'path'    => $request->path(),
            ]);

            return $this->requirePayment($request, $routeConfig, 'Settlement was rejected by the facilitator. Verify your payment authorization is valid and has not expired.');
        }

        // Attach payment metadata to request for downstream use
        $request->attributes->set('x402_payment', [
            'payer'       => $settleResponse->payer,
            'transaction' => $settleResponse->transaction,
            'network'     => $settleResponse->network,
            'amount'      => $routeConfig->price,
        ]);

        // Process the actual request
        $response = $next($request);

        // Add PAYMENT-RESPONSE header to the response
        $response->headers->set('PAYMENT-RESPONSE', $settleResponse->toBase64());

        return $response;
    }

    /**
     * Return a 402 Payment Required response with requirements header.
     */
    private function requirePayment(Request $request, MonetizedRouteConfig $routeConfig, ?string $error = null): Response
    {
        $paymentRequired = $this->pricing->buildPaymentRequired($request, $routeConfig, $error);
        $encodedHeader = $this->codec->encodePaymentRequired($paymentRequired);

        X402PaymentRequested::dispatch(
            $request->method(),
            $request->path(),
            $routeConfig->network,
            $routeConfig->price,
            (string) config('x402.server.pay_to'),
        );

        $body = [
            'error'   => 'Payment Required',
            'message' => $error ?? 'This endpoint requires payment via the x402 protocol.',
            'code'    => 'X402_PAYMENT_REQUIRED',
            'x402'    => $paymentRequired->toArray(),
        ];

        return response()->json($body, 402)
            ->withHeaders([
                'PAYMENT-REQUIRED'   => $encodedHeader,
                'X-Payment-Protocol' => 'x402',
                'X-Payment-Version'  => (string) config('x402.version', 2),
            ]);
    }
}
