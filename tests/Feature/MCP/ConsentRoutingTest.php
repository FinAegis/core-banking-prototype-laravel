<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    // Passport's AuthorizationServer requires a private key on construction.
    // The /oauth/authorize handler eagerly resolves it before invoking the
    // authorization view, so we need a real keypair for the request to reach
    // the consent screen. Inject a fresh in-memory pair instead of running
    // `passport:keys` (which would write keys to storage/).
    if (! config('passport.private_key')) {
        $resource = openssl_pkey_new([
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new RuntimeException('OpenSSL keypair generation failed in test environment.');
        }

        openssl_pkey_export($resource, $privateKey);

        $details = openssl_pkey_get_details($resource);
        if ($details === false || ! is_string($details['key'] ?? null)) {
            throw new RuntimeException('OpenSSL public key extraction failed in test environment.');
        }

        config([
            'passport.private_key' => $privateKey,
            'passport.public_key'  => $details['key'],
        ]);
    }
});

it('renders the custom MCP consent screen on /oauth/authorize', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $clientId = (string) Str::uuid();
    DB::table('oauth_clients')->insert([
        'id'                  => $clientId,
        'owner_type'          => null,
        'owner_id'            => null,
        'name'                => 'Marketing MCP Bot',
        'secret'              => bcrypt('test-secret'),
        'provider'            => null,
        'redirect_uris'       => (string) json_encode(['http://localhost:9999/callback']),
        'grant_types'         => (string) json_encode(['authorization_code']),
        'revoked'             => false,
        'client_logo_url'     => null,
        'registration_method' => 'dcr',
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    $response = $this->actingAs($user, 'web')->get('/oauth/authorize?' . http_build_query([
        'client_id'     => $clientId,
        'response_type' => 'code',
        'redirect_uri'  => 'http://localhost:9999/callback',
        'scope'         => 'accounts:read payments:write',
        'state'         => 'feature-test-state',
    ]));

    $response->assertStatus(200);
    $response->assertSee('Marketing MCP Bot', escape: false);
    $response->assertSee(config('mcp.scopes.accounts:read'), escape: false);
    $response->assertSee(config('mcp.scopes.payments:write'), escape: false);
    // The state is round-tripped through the form
    $response->assertSee('feature-test-state', escape: false);
});

it('round-trips the approve POST to /oauth/authorize and redirects with code+state', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $clientId = (string) Str::uuid();
    DB::table('oauth_clients')->insert([
        'id'                  => $clientId,
        'owner_type'          => null,
        'owner_id'            => null,
        'name'                => 'Round-Trip MCP App',
        'secret'              => bcrypt('test-secret'),
        'provider'            => null,
        'redirect_uris'       => (string) json_encode(['http://localhost:9999/callback']),
        'grant_types'         => (string) json_encode(['authorization_code']),
        'revoked'             => false,
        'client_logo_url'     => null,
        'registration_method' => 'dcr',
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    // Step 1: GET /oauth/authorize — Passport renders our consent screen, stores
    // an `authToken` in session, and emits it as the form's hidden auth_token field.
    $response = $this->actingAs($user, 'web')->get('/oauth/authorize?' . http_build_query([
        'client_id'     => $clientId,
        'response_type' => 'code',
        'redirect_uri'  => 'http://localhost:9999/callback',
        'scope'         => 'accounts:read',
        'state'         => 'feature-test-state',
    ]));

    $response->assertStatus(200);

    // Step 2: extract auth_token (Passport's random token) and _token (Laravel CSRF) from HTML.
    $html = (string) $response->getContent();

    $authMatches = [];
    if (preg_match('/name="auth_token"\s+value="([^"]+)"/', $html, $authMatches) !== 1) {
        throw new RuntimeException('Could not extract auth_token from consent screen HTML.');
    }
    $authToken = $authMatches[1];
    expect($authToken)->not->toBe(''); // would be empty if C1 weren't fixed

    // Blade `@csrf` emits `<input type="hidden" name="_token" value="...">`.
    $csrfMatches = [];
    if (preg_match('/name="_token"\s+value="([^"]+)"/', $html, $csrfMatches) !== 1) {
        throw new RuntimeException('Could not extract _token (CSRF) from consent screen HTML.');
    }
    $csrfToken = $csrfMatches[1];
    expect($csrfToken)->not->toBe('');

    // Step 3: POST back to /oauth/authorize with the same form fields the browser would submit.
    // The session cookie carries the `authToken` Passport stored during the GET, and our hidden
    // form field's value must match it (RetrievesAuthRequestFromSession::getAuthRequestFromSession).
    $approve = $this->actingAs($user, 'web')->post('/oauth/authorize', [
        '_token'                => $csrfToken,
        'auth_token'            => $authToken,
        'state'                 => 'feature-test-state',
        'mcp_daily_limit_minor' => '50000',
    ]);

    // Step 4: Passport completes the auth code grant and 302s back to the redirect_uri
    // with ?code=...&state=feature-test-state.
    $approve->assertStatus(302);
    $location = (string) $approve->headers->get('Location');
    expect($location)->toStartWith('http://localhost:9999/callback');
    expect($location)->toContain('state=feature-test-state');
    expect($location)->toContain('code=');
});
