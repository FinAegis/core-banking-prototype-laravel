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
