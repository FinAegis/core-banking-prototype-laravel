<?php

declare(strict_types=1);

it('registers a DCR client and returns credentials', function () {
    $response = $this->postJson('/oauth/register', [
        'client_name'   => 'Test MCP Client',
        'redirect_uris' => ['http://127.0.0.1:1234/callback', 'http://127.0.0.1:5678/callback'],
        'grant_types'   => ['authorization_code', 'refresh_token'],
        'scope'         => 'accounts:read payments:write',
        'logo_uri'      => 'https://example.com/logo.png',
        'tos_uri'       => 'https://example.com/tos',
        'policy_uri'    => 'https://example.com/privacy',
    ]);

    $response->assertStatus(201);
    $data = $response->json();

    expect($data)->toHaveKeys(['client_id', 'client_secret', 'client_name', 'redirect_uris', 'grant_types', 'token_endpoint_auth_method']);
    expect($data['client_name'])->toBe('Test MCP Client');
    expect($data['redirect_uris'])->toBe(['http://127.0.0.1:1234/callback', 'http://127.0.0.1:5678/callback']);

    $this->assertDatabaseHas('oauth_clients', [
        'id'                  => $data['client_id'],
        'name'                => 'Test MCP Client',
        'client_logo_url'     => 'https://example.com/logo.png',
        'registration_method' => 'dcr',
    ]);
});
