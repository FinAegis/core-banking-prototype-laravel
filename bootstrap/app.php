<?php

// SPDX-License-Identifier: Apache-2.0
// Copyright (c) 2024-2026 FinAegis Contributors

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        using: function () {
            $host = request()->getHost();
            $isApiSubdomain = str_starts_with($host, 'api.');
            $isProtocolSubdomain = str_starts_with($host, 'x402.') || str_starts_with($host, 'mpp.');

            // Health check route — must be registered inside `using` callback
            // because Laravel skips buildRoutingCallback when `using` is provided
            Route::get('/up', function () {
                $exception = null;

                try {
                    Event::dispatch(new DiagnosingHealth());
                } catch (Throwable $e) {
                    if (app()->hasDebugModeEnabled()) {
                        throw $e;
                    }

                    report($e);
                    $exception = $e->getMessage();
                }

                return response(View::file(
                    base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/health-up.blade.php'),
                    ['exception' => $exception],
                ), status: $exception ? 500 : 200);
            });

            // Always load console routes
            Route::group([], base_path('routes/console.php'));

            if ($isProtocolSubdomain) {
                // For x402.* or mpp.* subdomains, load API routes without /api prefix
                // and auto-apply the protocol payment gate middleware via ProtocolSubdomainMiddleware
                Route::middleware(['api', 'protocol.subdomain'])
                    ->group(base_path('routes/api.php'));

                Route::middleware(['api', 'protocol.subdomain'])
                    ->group(base_path('routes/api-bian.php'));

                Route::middleware(['api', 'protocol.subdomain'])
                    ->prefix('v2')
                    ->group(base_path('routes/api-v2.php'));
            } elseif ($isApiSubdomain) {
                // For api.* subdomain, ONLY load API routes without /api prefix
                Route::middleware('api')
                    ->group(base_path('routes/api.php'));

                Route::middleware('api')
                    ->group(base_path('routes/api-bian.php'));

                Route::middleware('api')
                    ->prefix('v2')
                    ->group(base_path('routes/api-v2.php'));
            } else {
                // For main domain, load web routes and API routes with prefix
                Route::middleware('web')
                    ->group(base_path('routes/web.php'));

                Route::middleware('api')
                    ->prefix('api')
                    ->group(base_path('routes/api.php'));

                Route::middleware('api')
                    ->prefix('api')
                    ->group(base_path('routes/api-bian.php'));

                Route::middleware('api')
                    ->prefix('api/v2')
                    ->group(base_path('routes/api-v2.php'));
            }
        },
        commands: __DIR__ . '/../routes/console.php',
    )
    ->withBroadcasting(base_path('routes/channels.php'), [
        'middleware' => ['api', 'auth:sanctum'],
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Register rate limiting middleware
        $middleware->alias([
            'api.rate_limit'         => App\Http\Middleware\ApiRateLimitMiddleware::class,
            'transaction.rate_limit' => App\Http\Middleware\TransactionRateLimitMiddleware::class,
            'ensure.json'            => App\Http\Middleware\EnsureJsonRequest::class,
            'check.token.expiration' => App\Http\Middleware\CheckTokenExpiration::class,
            'sub_product'            => App\Http\Middleware\EnsureSubProductEnabled::class,
            'auth.apikey'            => App\Http\Middleware\AuthenticateApiKey::class,
            'auth.api_or_sanctum'    => App\Http\Middleware\AuthenticateApiOrSanctum::class,
            'idempotency'            => App\Http\Middleware\IdempotencyMiddleware::class,
            'webhook.signature'      => App\Http\Middleware\ValidateWebhookSignature::class,
            'validate.key.access'    => App\Http\Middleware\ValidateKeyAccess::class,
            'demo'                   => App\Http\Middleware\DemoMode::class,
            'scope'                  => App\Http\Middleware\CheckApiScope::class,
            'check.blocked.ip'       => App\Http\Middleware\CheckBlockedIp::class,
            'ip.blocking'            => App\Http\Middleware\IpBlocking::class,
            'require.2fa.admin'      => App\Http\Middleware\RequireTwoFactorForAdmin::class,
            // Agent Protocol authentication middleware
            'auth.agent'       => App\Http\Middleware\AuthenticateAgentDID::class,
            'agent.scope'      => App\Http\Middleware\CheckAgentScope::class,
            'agent.capability' => App\Http\Middleware\CheckAgentCapability::class,
            // KYC verification gate — blocks financial operations for Level 0 users
            'require.kyc' => App\Http\Middleware\RequireKycVerification::class,
            // BaaS partner authentication middleware
            'partner.auth' => App\Http\Middleware\PartnerAuthMiddleware::class,
            // Multi-tenancy middleware
            'tenant' => App\Http\Middleware\InitializeTenancyByTeam::class,
            // Performance monitoring middleware
            'query.performance' => App\Http\Middleware\QueryPerformanceMiddleware::class,
            'metrics'           => App\Http\Middleware\MetricsMiddleware::class,
            'cache.performance' => App\Http\Middleware\CachePerformance::class,
            // Structured logging middleware (v3.3.0)
            'structured.logging' => App\Http\Middleware\StructuredLoggingMiddleware::class,
            // Distributed tracing middleware (v3.3.0)
            'tracing' => App\Http\Middleware\TracingMiddleware::class,
            // API versioning middleware (v3.4.0)
            'api.version' => App\Http\Middleware\ApiVersionMiddleware::class,
            // API deprecation headers middleware (v5.10.0)
            'deprecated' => App\Http\Middleware\ApiDeprecationMiddleware::class,
            // X402 Payment Protocol middleware (v5.2.0)
            'x402.payment' => App\Http\Middleware\X402PaymentGateMiddleware::class,
            // Machine Payments Protocol middleware (v6.4.0)
            'mpp.payment' => App\Http\Middleware\MppPaymentGateMiddleware::class,
            // WebSocket paid channel gate (v6.5.0)
            'ws.payment' => App\Http\Middleware\WebSocketPaymentGateMiddleware::class,
            // Protocol subdomain auto-detection (v6.5.0)
            'protocol.subdomain' => App\Http\Middleware\ProtocolSubdomainMiddleware::class,
        ]);

        // Prepend CORS middleware to handle it before other middleware
        $middleware->prepend(Illuminate\Http\Middleware\HandleCors::class);

        // Append SecurityHeaders globally to ensure all requests have security headers
        $middleware->append(App\Http\Middleware\SecurityHeaders::class);

        // Apply middleware to API routes (no global throttling - use custom rate limiting)
        $middleware->group('api', [
            App\Http\Middleware\IpBlocking::class,
            Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            Illuminate\Routing\Middleware\SubstituteBindings::class,
            App\Http\Middleware\SecurityHeaders::class,
            App\Http\Middleware\ApiVersionMiddleware::class,
            App\Http\Middleware\CheckTokenExpiration::class,
            App\Http\Middleware\EnforceMethodScope::class,
            // Observability middleware stack (v5.10.0)
            App\Http\Middleware\StructuredLoggingMiddleware::class,
            App\Http\Middleware\MetricsMiddleware::class,
            App\Http\Middleware\QueryPerformanceMiddleware::class,
            App\Http\Middleware\CachePerformance::class,
            App\Http\Middleware\TracingMiddleware::class,
        ]);

        // Apply security headers to web routes
        $middleware->group('web', [
            App\Http\Middleware\EncryptCookies::class,
            Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            Illuminate\Session\Middleware\StartSession::class,
            Illuminate\View\Middleware\ShareErrorsFromSession::class,
            App\Http\Middleware\VerifyCsrfToken::class,
            Illuminate\Routing\Middleware\SubstituteBindings::class,
            App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Apply demo middleware to web routes in demo environment
        // Note: env() is used here because config() is not available during middleware registration
        if (env('APP_ENV') === 'demo') {
            $middleware->appendToGroup('web', App\Http\Middleware\DemoMode::class);
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Treat 'demo' environment as production for error handling
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            // Check if we're on the API or protocol subdomain
            $reqHost = $request->getHost();
            if (str_starts_with($reqHost, 'api.') || str_starts_with($reqHost, 'x402.') || str_starts_with($reqHost, 'mpp.')) {
                return true;
            }

            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });

        // Standardize API error responses (v5.10.0)
        $exceptions->respond(function (Symfony\Component\HttpFoundation\Response $response, Throwable $e, Illuminate\Http\Request $request) {
            if (! $response instanceof Illuminate\Http\JsonResponse) {
                return $response;
            }

            $errHost = $request->getHost();
            $isApi = $request->is('api/*') || str_starts_with($errHost, 'api.') || str_starts_with($errHost, 'x402.') || str_starts_with($errHost, 'mpp.') || $request->expectsJson();
            if (! $isApi) {
                return $response;
            }

            $data = $response->getData(true);

            // Map exception types to error codes
            $errorCode = match (true) {
                $e instanceof Illuminate\Validation\ValidationException                            => 'VALIDATION_ERROR',
                $e instanceof Illuminate\Auth\AuthenticationException                              => 'UNAUTHENTICATED',
                $e instanceof Illuminate\Auth\Access\AuthorizationException                        => 'FORBIDDEN',
                $e instanceof Illuminate\Database\Eloquent\ModelNotFoundException                  => 'NOT_FOUND',
                $e instanceof Symfony\Component\HttpKernel\Exception\NotFoundHttpException         => 'NOT_FOUND',
                $e instanceof Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException => 'METHOD_NOT_ALLOWED',
                $e instanceof Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException  => 'RATE_LIMITED',
                $e instanceof Symfony\Component\HttpKernel\Exception\HttpException                 => 'HTTP_ERROR',
                default                                                                            => 'SERVER_ERROR',
            };

            // Add standardized fields without overwriting existing ones
            if (! isset($data['error'])) {
                $data['error'] = $errorCode;
            }
            if (! isset($data['request_id'])) {
                $data['request_id'] = $request->header('X-Request-ID');
            }

            $response->setData($data);

            return $response;
        });

        // Don't report certain exceptions in demo environment
        $exceptions->dontReport([
            Illuminate\Auth\AuthenticationException::class,
            Illuminate\Auth\Access\AuthorizationException::class,
            Symfony\Component\HttpKernel\Exception\HttpException::class,
            Illuminate\Database\Eloquent\ModelNotFoundException::class,
            Illuminate\Validation\ValidationException::class,
        ]);
    })->create();
