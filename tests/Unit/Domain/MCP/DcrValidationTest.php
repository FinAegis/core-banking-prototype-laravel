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
        'redirect_uris' => ['http://127.0.0.1:1234/callback'],
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
        'redirect_uris' => ['http://127.0.0.1:1234/callback'],
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
        'redirect_uris' => ['http://127.0.0.1:1234/callback'],
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
        'redirect_uris' => ['http://127.0.0.1:1234/callback'],
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
        'redirect_uris' => ['http://127.0.0.1:1234/callback'],
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

it('rejects DCR with non-https remote redirect_uri (auth-code interception risk)', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Test',
        'redirect_uris' => ['http://attacker.example.com/cb'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_redirect_uri');
    expect($response->getData(true)['error_description'])->toContain('https');
});

it('allows DCR for RFC 8252 loopback http redirect (native app pattern)', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Native App',
        'redirect_uris' => ['http://127.0.0.1:54321/callback'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(201);
});

it('allows DCR for a custom-scheme native app redirect', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Native App',
        'redirect_uris' => ['com.example.app://oauth/callback'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(201);
});

it('rejects DCR with javascript: redirect_uri (XSS / auth-code exfil vector)', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Test',
        'redirect_uris' => ['javascript:alert(document.cookie)'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_redirect_uri');
});

it('rejects DCR with data: redirect_uri', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Test',
        'redirect_uris' => ['data:text/html,<script>fetch(location.search)</script>'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_redirect_uri');
});

it('rejects DCR with file:// redirect_uri', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Test',
        'redirect_uris' => ['file:///etc/passwd'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_redirect_uri');
});

it('rejects DCR with bare app-name scheme (collision risk per RFC 8252)', function () {
    // `myapp://` could be claimed by any app on the device — reverse-DNS form
    // (`com.example.myapp://`) ties the scheme to a domain the developer owns.
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Test',
        'redirect_uris' => ['myapp://oauth/callback'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_redirect_uri');
});

it('rejects DCR with http://localhost (must be 127.0.0.1 per RFC 8252)', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], (string) json_encode([
        'client_name'   => 'Test',
        'redirect_uris' => ['http://localhost:1234/callback'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_redirect_uri');
});
