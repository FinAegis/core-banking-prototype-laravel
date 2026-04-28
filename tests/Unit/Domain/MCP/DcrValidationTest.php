<?php

declare(strict_types=1);

use App\Domain\MCP\Auth\DynamicClientRegistrationController;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

it('rejects DCR with no redirect_uris', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode(['client_name' => 'Test']));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_redirect_uri');
});

it('rejects DCR with invalid grant_types', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Test',
        'redirect_uris' => ['http://localhost:1234/callback'],
        'grant_types'   => ['password'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_client_metadata');
});

it('rejects DCR with non-https logo_uri', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Test',
        'redirect_uris' => ['http://localhost:1234/callback'],
        'logo_uri'      => 'http://evil.example.com/spoof.png', // http, not https
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_client_metadata');
    expect($response->getData(true)['error_description'])->toContain('logo_uri');
});

it('rejects DCR with malformed tos_uri', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Test',
        'redirect_uris' => ['http://localhost:1234/callback'],
        'tos_uri'       => 'not-a-url',
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_client_metadata');
    expect($response->getData(true)['error_description'])->toContain('tos_uri');
});

it('rejects DCR with non-https policy_uri', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Test',
        'redirect_uris' => ['http://localhost:1234/callback'],
        'policy_uri'    => 'ftp://example.com/privacy',
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_client_metadata');
    expect($response->getData(true)['error_description'])->toContain('policy_uri');
});

it('rejects DCR with non-https client_uri', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Test',
        'redirect_uris' => ['http://localhost:1234/callback'],
        'client_uri'    => 'http://example.com',
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_client_metadata');
    expect($response->getData(true)['error_description'])->toContain('client_uri');
});

it('rejects DCR with a reserved client_name (exact match)', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Zelta',
        'redirect_uris' => ['https://example.com/cb'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_client_metadata');
    expect($response->getData(true)['error_description'])->toContain('reserved keyword');
});

it('rejects DCR with a reserved substring inside the client_name', function () {
    // "Zelta Official Bot" contains both "zelta" and "official" — should match the
    // first reserved keyword found (case-insensitive substring match).
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Zelta Official Bot',
        'redirect_uris' => ['https://example.com/cb'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error_description'])->toContain('reserved keyword');
});

it('rejects DCR with brand impersonation across known partners', function () {
    foreach (['stripe', 'anthropic', 'claude', 'finaegis'] as $brand) {
        $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
            'client_name'   => ucfirst($brand) . ' Connector',
            'redirect_uris' => ['https://example.com/cb'],
        ]));
        $req->headers->set('Content-Type', 'application/json');
        $response = (new DynamicClientRegistrationController())->__invoke($req);
        expect($response->getStatusCode())->toBe(400, "{$brand} should be reserved");
        expect($response->getData(true)['error'])->toBe('invalid_client_metadata');
    }
});

it('allows DCR for a non-reserved client_name', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Acme Trading Assistant',
        'redirect_uris' => ['https://example.com/cb'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(201);
    expect($response->getData(true))->toHaveKey('client_id');
});
