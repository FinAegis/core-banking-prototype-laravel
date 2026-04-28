<?php

declare(strict_types=1);

use App\Domain\MCP\Discovery\ProtectedResourceMetadataController;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

it('emits RFC 9728 protected resource metadata', function () {
    $controller = new ProtectedResourceMetadataController();
    $response = $controller(Request::create('/.well-known/oauth-protected-resource'));
    $body = $response->getData(true);

    expect($body)->toHaveKeys(['resource', 'authorization_servers', 'scopes_supported', 'bearer_methods_supported']);
    expect($body['resource'])->toBe(config('mcp.resource_uri'));
    expect($body['authorization_servers'])->toBe([config('mcp.authorization_server')]);
    expect($body['scopes_supported'])->toContain('payments:write', 'sms:send');
    expect($body['bearer_methods_supported'])->toBe(['header']);
});

it('emits OAuth Authorization Server metadata', function () {
    $controller = new App\Domain\MCP\Discovery\AuthorizationServerMetadataController();
    $body = $controller(Request::create('/.well-known/oauth-authorization-server'))->getData(true);

    expect($body)->toHaveKeys([
        'issuer',
        'authorization_endpoint',
        'token_endpoint',
        'registration_endpoint',
        'scopes_supported',
        'response_types_supported',
        'grant_types_supported',
        'code_challenge_methods_supported',
        'token_endpoint_auth_methods_supported',
    ]);
    expect($body['issuer'])->toBe(config('mcp.authorization_server'));
    expect($body['authorization_endpoint'])->toBe(config('mcp.authorization_server') . '/oauth/authorize');
    expect($body['token_endpoint'])->toBe(config('mcp.authorization_server') . '/oauth/token');
    expect($body['registration_endpoint'])->toBe(config('mcp.authorization_server') . '/oauth/register');
    expect($body['grant_types_supported'])->toContain('authorization_code', 'client_credentials', 'refresh_token');
    expect($body['code_challenge_methods_supported'])->toBe(['S256']);
});
