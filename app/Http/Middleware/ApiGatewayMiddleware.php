<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiGatewayMiddleware
{
    /**
     * Request ID header for distributed tracing.
     */
    private const REQUEST_ID_HEADER = 'X-Request-Id';

    /**
     * API version header.
     */
    private const API_VERSION_HEADER = 'X-API-Version';

    /**
     * Gateway timing header.
     */
    private const TIMING_HEADER = 'X-Gateway-Timing';

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Ensure request has a unique ID for tracing
        $requestId = $request->header(self::REQUEST_ID_HEADER)
            ?? $this->generateRequestId();
        $request->headers->set(self::REQUEST_ID_HEADER, $requestId);

        // Log incoming request
        Log::channel('single')->debug('API Gateway: incoming request', [
            'request_id' => $requestId,
            'method'     => $request->method(),
            'path'       => $request->path(),
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Process the request
        $response = $next($request);

        // Add gateway headers to response
        $elapsed = round((microtime(true) - $startTime) * 1000, 2);

        if ($response instanceof \Illuminate\Http\Response || $response instanceof \Illuminate\Http\JsonResponse) {
            $response->headers->set(self::REQUEST_ID_HEADER, $requestId);
            $response->headers->set(self::API_VERSION_HEADER, config('app.version', '5.0.0'));
            $response->headers->set(self::TIMING_HEADER, "{$elapsed}ms");
            $response->headers->remove('X-Powered-By');
        }

        // Log response
        Log::channel('single')->debug('API Gateway: response sent', [
            'request_id' => $requestId,
            'status'     => $response->getStatusCode(),
            'timing_ms'  => $elapsed,
        ]);

        return $response;
    }

    private function generateRequestId(): string
    {
        return sprintf(
            'req_%s_%s',
            now()->format('YmdHis'),
            bin2hex(random_bytes(8))
        );
    }
}
