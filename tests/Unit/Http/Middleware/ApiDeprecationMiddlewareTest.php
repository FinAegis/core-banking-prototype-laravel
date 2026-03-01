<?php

declare(strict_types=1);

use App\Http\Middleware\ApiDeprecationMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

uses(Tests\TestCase::class);

describe('ApiDeprecationMiddleware', function () {
    it('adds Deprecation header', function () {
        $middleware = new ApiDeprecationMiddleware();
        $request = Request::create('/api/profile', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        expect($response->headers->get('Deprecation'))->toBe('true');
    });

    it('adds Link header with successor version', function () {
        $middleware = new ApiDeprecationMiddleware();
        $request = Request::create('/api/profile', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        expect($response->headers->get('Link'))->toBe('</api/v2>; rel="successor-version"');
    });

    it('adds Sunset header when date provided', function () {
        $middleware = new ApiDeprecationMiddleware();
        $request = Request::create('/api/profile', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok'), '2026-09-01');

        expect($response->headers->get('Sunset'))->toBe('2026-09-01');
    });

    it('omits Sunset header when no date provided', function () {
        $middleware = new ApiDeprecationMiddleware();
        $request = Request::create('/api/profile', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        expect($response->headers->has('Sunset'))->toBeFalse();
    });

    it('does not modify response body', function () {
        $middleware = new ApiDeprecationMiddleware();
        $request = Request::create('/api/profile', 'GET');

        $response = $middleware->handle($request, fn () => new Response('original content'));

        expect($response->getContent())->toBe('original content');
    });
});
