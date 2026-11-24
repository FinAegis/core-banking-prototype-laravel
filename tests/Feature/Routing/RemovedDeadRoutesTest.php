<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// These routes pointed at controller methods that did not exist, or at pages
// that read tables no migration creates. They were removed rather than built:
// - v2 asset writes: operators manage assets in the admin panel, and the
//   routes had no administrator check.
// - v2 rate refresh: operators use POST /api/v1/exchange-providers/refresh.
// - v2 transactions: no model or resource backs a cross-account list; the
//   per-account history is GET /api/v2/accounts/{uuid}/transactions.
// - External exchange arbitrage and price alignment pages: their tables do
//   not exist, and arbitrage execution would move funds.

it('does not register the removed route', function (string $method, string $uri): void {
    $request = Request::create($uri, $method);
    $match = collect(Route::getRoutes()->get($method))
        ->first(fn ($route): bool => $route->matches($request));

    expect($match)->toBeNull();
})->with([
    ['POST', '/api/v2/assets'],
    ['PUT', '/api/v2/assets/EUR'],
    ['DELETE', '/api/v2/assets/EUR'],
    ['POST', '/api/v2/exchange-rates/refresh'],
    ['GET', '/api/v2/transactions'],
    ['GET', '/api/v2/transactions/123'],
    ['GET', '/exchange/external/arbitrage'],
    ['POST', '/exchange/external/arbitrage/execute'],
    ['GET', '/exchange/external/price-alignment'],
    ['PUT', '/exchange/external/price-alignment'],
]);

it('keeps the read routes and the operator rate refresh', function (string $method, string $uri): void {
    $request = Request::create($uri, $method);
    $match = collect(Route::getRoutes()->get($method))
        ->first(fn ($route): bool => $route->matches($request));

    expect($match)->not->toBeNull();
})->with([
    ['GET', '/api/v2/assets'],
    ['GET', '/api/v2/assets/EUR'],
    ['GET', '/api/v2/exchange-rates'],
    ['GET', '/api/v2/accounts/9b2f3c1e-4d5a-4b6c-8d7e-0f1a2b3c4d5e/transactions'],
    ['POST', '/api/v1/exchange-providers/refresh'],
    ['GET', '/exchange/external'],
]);
