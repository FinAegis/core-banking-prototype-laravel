<?php

declare(strict_types=1);

// Regression coverage for the Apple App Site Association (AASA) file.
//
// Apple is strict: the appID must be "<TeamID>.<BundleID>". A missing/empty
// team-id prefix (".com.zelta.wallet") silently breaks iOS passkey sign-in and
// the /pay + /verify universal links. These tests pin the team-id prefix and
// guard against the empty-prefix regression.

const AASA_BUNDLE_ID = 'com.zelta.wallet';
const AASA_DEFAULT_TEAM_ID = 'V6JGV96RCA';

it('serves the AASA file at the exact path with a JSON content type and no redirect', function () {
    $response = $this->get('/.well-known/apple-app-site-association');

    $response->assertOk(); // 200 directly, not a 3xx redirect
    expect($response->headers->get('Content-Type'))->toContain('application/json');
});

it('serves an appID with a non-empty Apple Team ID prefix', function () {
    $payload = $this->get('/.well-known/apple-app-site-association')->json();

    $appId = $payload['applinks']['details'][0]['appID'];
    $webcred = $payload['webcredentials']['apps'][0];

    // The empty-prefix bug produced ".com.zelta.wallet" — guard both fields.
    expect($appId)->not->toStartWith('.');
    expect($webcred)->not->toStartWith('.');

    expect($appId)->toBe(AASA_DEFAULT_TEAM_ID . '.' . AASA_BUNDLE_ID);
    expect($webcred)->toBe(AASA_DEFAULT_TEAM_ID . '.' . AASA_BUNDLE_ID);
});

it('declares the /pay and /verify universal-link paths', function () {
    $payload = $this->get('/.well-known/apple-app-site-association')->json();

    expect($payload['applinks']['details'][0]['paths'])->toBe(['/pay/*', '/verify/*']);
    expect($payload['applinks']['apps'])->toBe([]);
});

it('respects an explicit Apple Team ID override from config', function () {
    config(['mobile.apple_team_id' => 'ABCDE12345']);

    $payload = $this->get('/.well-known/apple-app-site-association')->json();

    expect($payload['applinks']['details'][0]['appID'])->toBe('ABCDE12345.' . AASA_BUNDLE_ID);
    expect($payload['webcredentials']['apps'][0])->toBe('ABCDE12345.' . AASA_BUNDLE_ID);
});
