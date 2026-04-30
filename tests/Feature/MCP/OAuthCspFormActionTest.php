<?php

declare(strict_types=1);

it('allows loopback redirect_uris in form-action CSP for OAuth pages', function () {
    $response = $this->get('/oauth/authorize?response_type=code&client_id=x&redirect_uri=http%3A%2F%2F127.0.0.1%3A53682%2Fcallback');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain('form-action');
    expect($csp)->toContain('http://127.0.0.1:*');
    expect($csp)->toContain('http://[::1]:*');
    expect($csp)->toContain('https:');
});

it('keeps strict form-action on non-OAuth pages', function () {
    $response = $this->get('/');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("form-action 'self'");
    expect($csp)->not->toContain('http://127.0.0.1:*');
});
