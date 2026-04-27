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
