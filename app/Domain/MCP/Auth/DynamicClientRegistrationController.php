<?php

declare(strict_types=1);

namespace App\Domain\MCP\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\ClientRepository;

final class DynamicClientRegistrationController
{
    private const ALLOWED_GRANT_TYPES = ['authorization_code', 'client_credentials', 'refresh_token'];

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        $redirectUris = $payload['redirect_uris'] ?? [];
        if (! is_array($redirectUris) || $redirectUris === []) {
            return $this->error('invalid_redirect_uri', 'redirect_uris is required and must be a non-empty array');
        }

        foreach ($redirectUris as $uri) {
            if (! is_string($uri) || ! filter_var($uri, FILTER_VALIDATE_URL)) {
                return $this->error('invalid_redirect_uri', "redirect_uri is not a valid URL: {$uri}");
            }
        }

        $grantTypes = $payload['grant_types'] ?? ['authorization_code'];
        foreach ($grantTypes as $gt) {
            if (! in_array($gt, self::ALLOWED_GRANT_TYPES, true)) {
                return $this->error('invalid_client_metadata', "unsupported grant_type: {$gt}");
            }
        }

        $clientName = (string) ($payload['client_name'] ?? 'Unnamed MCP Client');

        /** @var ClientRepository $repo */
        $repo = app(ClientRepository::class);

        $client = $repo->createAuthorizationCodeGrantClient(
            name: $clientName,
            redirectUris: $redirectUris,
            confidential: true,
        );

        $client->forceFill([
            'client_logo_url'     => $payload['logo_uri'] ?? null,
            'client_terms_url'    => $payload['tos_uri'] ?? null,
            'client_privacy_url'  => $payload['policy_uri'] ?? null,
            'dcr_metadata_uri'    => $payload['client_uri'] ?? null,
            'registration_method' => 'dcr',
        ])->save();

        return response()->json([
            'client_id'                  => $client->getKey(),
            'client_secret'              => $client->plainSecret,
            'client_name'                => $client->name,
            'redirect_uris'              => $redirectUris,
            'grant_types'                => $grantTypes,
            'token_endpoint_auth_method' => 'client_secret_basic',
            'logo_uri'                   => $client->client_logo_url,
            'tos_uri'                    => $client->client_terms_url,
            'policy_uri'                 => $client->client_privacy_url,
            'client_id_issued_at'        => now()->timestamp,
        ], 201);
    }

    private function error(string $code, string $description): JsonResponse
    {
        return response()->json([
            'error'             => $code,
            'error_description' => $description,
        ], 400);
    }
}
