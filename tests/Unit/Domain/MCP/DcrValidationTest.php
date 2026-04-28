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
