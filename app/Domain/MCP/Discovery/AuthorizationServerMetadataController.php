<?php

declare(strict_types=1);

namespace App\Domain\MCP\Discovery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthorizationServerMetadataController
{
    public function __invoke(Request $request): JsonResponse
    {
        $issuer = (string) config('mcp.authorization_server');

        return response()->json([
            'issuer'                                => $issuer,
            'authorization_endpoint'                => $issuer . '/oauth/authorize',
            'token_endpoint'                        => $issuer . '/oauth/token',
            'registration_endpoint'                 => $issuer . '/oauth/register',
            'revocation_endpoint'                   => $issuer . '/oauth/tokens',
            'scopes_supported'                      => array_keys((array) config('mcp.scopes', [])),
            'response_types_supported'              => ['code'],
            'grant_types_supported'                 => ['authorization_code', 'client_credentials', 'refresh_token'],
            'code_challenge_methods_supported'      => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
