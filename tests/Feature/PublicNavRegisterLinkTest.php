<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

// Production disables the Fortify registration feature (REGISTRATION_ENABLED
// defaults to off outside local/testing/demo), so the named route `register`
// does not exist there. The public navigation must still render, otherwise
// every page on the public layout (terms, privacy policy, status) answers 500.

function removeNamedRoute(string $name): void
{
    $kept = new RouteCollection();

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if ($route->getName() !== $name) {
            $kept->add($route);
        }
    }

    Route::setRoutes($kept);
}

it('renders the public navigation without a register route', function (): void {
    removeNamedRoute('register');

    expect(Route::has('register'))->toBeFalse();

    $html = view('partials.public-nav')->render();

    expect($html)->toContain('Sign in');
    expect(str_contains($html, 'Get Started'))->toBeFalse();
});

it('shows the register link when the register route exists', function (): void {
    expect(Route::has('register'))->toBeTrue();

    $html = view('partials.public-nav')->render();

    expect($html)->toContain('Get Started');
});
