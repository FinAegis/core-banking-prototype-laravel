<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Monitoring\Services\TracingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TracingMiddleware
{
    /**
     * Request fields never copied into an error trace or its log line:
     * passwords, one-time codes (the card withdrawal code among them) and
     * secrets.
     */
    private const REDACTED = ['password', 'password_confirmation', 'current_password', 'code', 'otp', 'otp_code', 'pin', 'cvv', 'token', 'secret'];

    public function __construct(
        private readonly TracingService $tracingService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Extract trace context from incoming request headers
        $this->tracingService->extractContext($request->headers->all());

        // Start a new trace for this request
        $traceId = $this->tracingService->startTrace(
            $request->method() . ' ' . $request->path(),
            [
                'http.method'     => $request->method(),
                'http.url'        => $request->fullUrl(),
                'http.target'     => $request->path(),
                'http.host'       => $request->getHost(),
                'http.scheme'     => $request->getScheme(),
                'http.user_agent' => $request->userAgent(),
                'http.client_ip'  => $request->ip(),
                'user.id'         => $request->user()?->id,
            ]
        );

        try {
            // Process the request
            $response = $next($request);

            // Set response attributes
            $this->tracingService->setAttribute($traceId, 'http.status_code', $response->getStatusCode());
            // Streamed and file responses have no buffered body (getContent() is false).
            $content = $response->getContent();
            if (is_string($content)) {
                $this->tracingService->setAttribute($traceId, 'http.response_size', strlen($content));
            }

            // End span with success status
            $status = $response->getStatusCode() >= 400 ? 'error' : 'ok';
            $this->tracingService->endSpan($traceId, $status);

            // Inject trace context into response headers for distributed tracing
            $headers = [];
            $this->tracingService->injectContext($headers);
            foreach ($headers as $key => $value) {
                $response->headers->set($key, $value);
            }

            return $response;
        } catch (Throwable $e) {
            // Record error
            $this->tracingService->recordError($traceId, $e, [
                'request' => [
                    'method' => $request->method(),
                    'path'   => $request->path(),
                    'input'  => Arr::except($request->all(), self::REDACTED),
                ],
            ]);

            // End span with error status
            $this->tracingService->endSpan($traceId, 'error');

            // Re-throw the exception
            throw $e;
        } finally {
            // Ensure trace is ended
            $this->tracingService->endTrace();
        }
    }
}
