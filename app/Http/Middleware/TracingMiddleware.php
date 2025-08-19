<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Monitoring\Services\TracingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TracingMiddleware
{
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
            $this->tracingService->setAttribute($traceId, 'http.response_size', strlen($response->getContent()));

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
        } catch (\Throwable $e) {
            // Record error
            $this->tracingService->recordError($traceId, $e, [
                'request' => [
                    'method' => $request->method(),
                    'path'   => $request->path(),
                    'input'  => $request->all(),
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
