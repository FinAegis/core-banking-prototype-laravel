<?php

declare(strict_types=1);

namespace App\Domain\MCP\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\ClientRepository;

final class DynamicClientRegistrationController
{
    private const ALLOWED_GRANT_TYPES = ['authorization_code', 'client_credentials', 'refresh_token'];

    /**
     * Branding/legal metadata fields that must be HTTPS URLs when present.
     * The consent screen renders `logo_uri` directly in an `<img src>`, so any
     * unvalidated/non-HTTPS value would be a logo-spoofing phishing vector.
     */
    private const HTTPS_URL_FIELDS = ['logo_uri', 'tos_uri', 'policy_uri', 'client_uri'];

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

            // OAuth 2.1 §4.1.3: redirect URIs MUST use https except for native
            // app loopback (RFC 8252 §7.3 — http://127.0.0.1:port and
            // http://[::1]:port) or non-http custom schemes (e.g. com.app://).
            // FILTER_VALIDATE_URL accepts ftp://, http://example.com, etc., so
            // we re-check the scheme here. Without this, a network attacker
            // can intercept authorization codes for any registered http://
            // client.
            $scheme = parse_url($uri, PHP_URL_SCHEME);
            if (! is_string($scheme)) {
                return $this->error('invalid_redirect_uri', "redirect_uri is missing a scheme: {$uri}");
            }

            if ($scheme === 'https') {
                continue;
            }

            if ($scheme === 'http' && $this->isLoopbackUri($uri)) {
                continue;
            }

            // Custom/native app scheme: accept ONLY reverse-DNS form (`com.example.app://...`).
            // Bare schemes like `myapp://` are rejected because they collide with other apps.
            // Dangerous schemes (`javascript:`, `data:`, `file:`, `vbscript:`, `blob:`) would
            // execute in the user's browser if echoed back as an authorization redirect — an
            // auth-code interception + stored-XSS chain.
            if ($this->isAcceptableNativeScheme($scheme)) {
                continue;
            }

            return $this->error(
                'invalid_redirect_uri',
                "redirect_uri must be https, http://127.0.0.1 (RFC 8252 loopback), or a reverse-DNS native scheme (com.example.app://): {$uri}",
            );
        }

        $grantTypes = $payload['grant_types'] ?? ['authorization_code'];
        foreach ($grantTypes as $gt) {
            if (! in_array($gt, self::ALLOWED_GRANT_TYPES, true)) {
                return $this->error('invalid_client_metadata', "unsupported grant_type: {$gt}");
            }
        }

        foreach (self::HTTPS_URL_FIELDS as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null) {
                continue;
            }
            $value = $payload[$field];
            if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
                return $this->error('invalid_client_metadata', "{$field} must be an https URL");
            }
            if (parse_url($value, PHP_URL_SCHEME) !== 'https') {
                return $this->error('invalid_client_metadata', "{$field} must be an https URL");
            }
        }

        $clientName = (string) ($payload['client_name'] ?? 'Unnamed MCP Client');

        if ($reserved = $this->matchReservedSubstring($clientName)) {
            return $this->error(
                'invalid_client_metadata',
                "client_name contains reserved keyword '{$reserved}' — use a name that doesn't impersonate a known brand",
            );
        }

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

    /**
     * RFC 8252 §7.3 loopback redirect: http://127.0.0.1[:port] or http://[::1][:port].
     * Per the spec, the port may be ANY value (clients pick a free port at runtime).
     * `localhost` is NOT a valid loopback redirect — only the literal IP forms.
     */
    private function isLoopbackUri(string $uri): bool
    {
        $host = parse_url($uri, PHP_URL_HOST);

        return $host === '127.0.0.1' || $host === '[::1]' || $host === '::1';
    }

    /**
     * RFC 8252 §7.1 / §8.5: native-app custom schemes should be the reverse-DNS
     * form of a domain the app developer controls (`com.example.app`). The
     * pattern requires at least one dot and only [a-z0-9.-] characters, which
     * rules out `javascript:`, `data:`, `file:`, `vbscript:`, `blob:`,
     * `view-source:`, `chrome-extension:`, and bare app-name schemes that
     * could collide with another installed app.
     */
    private function isAcceptableNativeScheme(string $scheme): bool
    {
        // Reject http/https — those are validated separately.
        if ($scheme === 'http' || $scheme === 'https') {
            return false;
        }

        return preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $scheme) === 1;
    }

    private function error(string $code, string $description): JsonResponse
    {
        return response()->json([
            'error'             => $code,
            'error_description' => $description,
        ], 400);
    }

    /**
     * Return the first reserved substring found in $clientName, or null if none match.
     * Comparison is case-insensitive substring; a name like "ZeltaBot" matches "zelta".
     */
    private function matchReservedSubstring(string $clientName): ?string
    {
        $needle = strtolower($clientName);
        $reserved = (array) config('mcp.dcr.reserved_name_substrings', []);

        foreach ($reserved as $entry) {
            if (! is_string($entry) || $entry === '') {
                continue;
            }
            if (str_contains($needle, strtolower($entry))) {
                return $entry;
            }
        }

        return null;
    }
}
