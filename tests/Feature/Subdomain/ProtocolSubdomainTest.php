<?php

declare(strict_types=1);

use App\Http\Middleware\ProtocolSubdomainMiddleware;
use App\Http\Middleware\X402PaymentGateMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

describe('ProtocolSubdomainMiddleware', function (): void {
    it('sets x402 payment protocol for x402.api subdomain', function (): void {
        $middleware = app(ProtocolSubdomainMiddleware::class);
        $request = Request::create('https://x402.api.zelta.app/v1/x402/status');
        $request->headers->set('host', 'x402.api.zelta.app');

        $response = $middleware->handle($request, function (Request $req): Response {
            // The middleware delegates to X402PaymentGateMiddleware which may
            // pass through if x402 is not enabled or no monetized endpoint matches
            return new Response('ok');
        });

        expect($request->attributes->get('payment_protocol'))->toBe('x402');
    });

    it('sets mpp payment protocol for mpp.api subdomain', function (): void {
        $middleware = app(ProtocolSubdomainMiddleware::class);
        $request = Request::create('https://mpp.api.zelta.app/v1/mpp/status');
        $request->headers->set('host', 'mpp.api.zelta.app');

        $response = $middleware->handle($request, function (Request $req): Response {
            return new Response('ok');
        });

        expect($request->attributes->get('payment_protocol'))->toBe('mpp');
    });

    it('sets x402 protocol for bare x402 subdomain', function (): void {
        $middleware = app(ProtocolSubdomainMiddleware::class);
        $request = Request::create('https://x402.zelta.app/v1/x402/status');
        $request->headers->set('host', 'x402.zelta.app');

        $response = $middleware->handle($request, function (Request $req): Response {
            return new Response('ok');
        });

        expect($request->attributes->get('payment_protocol'))->toBe('x402');
    });

    it('does not set protocol for regular api subdomain', function (): void {
        $middleware = app(ProtocolSubdomainMiddleware::class);
        $request = Request::create('https://api.zelta.app/v1/x402/status');
        $request->headers->set('host', 'api.zelta.app');

        $response = $middleware->handle($request, function (Request $req): Response {
            return new Response('ok');
        });

        expect($request->attributes->get('payment_protocol'))->toBeNull();
    });

    it('does not set protocol for main domain', function (): void {
        $middleware = app(ProtocolSubdomainMiddleware::class);
        $request = Request::create('https://zelta.app/api/v1/x402/status');
        $request->headers->set('host', 'zelta.app');

        $response = $middleware->handle($request, function (Request $req): Response {
            return new Response('ok');
        });

        expect($request->attributes->get('payment_protocol'))->toBeNull();
    });
});
