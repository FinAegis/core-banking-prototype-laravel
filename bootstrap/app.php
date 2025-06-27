<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api-bian.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register rate limiting middleware
        $middleware->alias([
            'api.rate_limit' => \App\Http\Middleware\ApiRateLimitMiddleware::class,
            'transaction.rate_limit' => \App\Http\Middleware\TransactionRateLimitMiddleware::class,
            'ensure.json' => \App\Http\Middleware\EnsureJsonRequest::class,
            'check.token.expiration' => \App\Http\Middleware\CheckTokenExpiration::class,
            'sub_product' => \App\Http\Middleware\EnsureSubProductEnabled::class,
        ]);
        
        // Apply middleware to API routes (no global throttling - use custom rate limiting)
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
