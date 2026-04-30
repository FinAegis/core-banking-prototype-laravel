<?php

declare(strict_types=1);

it('auto-injects googletagmanager into script-src when brand.ga_id is set', function () {
    config(['brand.ga_id' => 'G-TESTID12345']);

    $response = $this->get('/');
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain('https://www.googletagmanager.com');
});

it('auto-injects google-analytics + doubleclick hosts into connect-src when brand.ga_id is set', function () {
    config(['brand.ga_id' => 'G-TESTID12345']);

    $response = $this->get('/');
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain('https://www.google-analytics.com');
    expect($csp)->toContain('https://stats.g.doubleclick.net');
});

it('does not inject GA hosts when brand.ga_id is empty', function () {
    config(['brand.ga_id' => '']);
    config(['security.csp.script_sources' => '']);
    config(['security.csp.connect_sources' => '']);

    $response = $this->get('/');
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('googletagmanager');
    expect($csp)->not->toContain('google-analytics');
});
