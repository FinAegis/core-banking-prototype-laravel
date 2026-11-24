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

/**
 * Walk the consent screen as the browser does and approve with the given
 * limit field. Returns the client id and the approve response.
 *
 * @return array{0: string, 1: Illuminate\Testing\TestResponse<Symfony\Component\HttpFoundation\Response>}
 */
function approveConsent(PHPUnit\Framework\TestCase $test, User $user, ?string $limitField, ?string $clientId = null): array
{
    $clientId ??= (string) Str::uuid();
    if (! DB::table('oauth_clients')->where('id', $clientId)->exists()) {
        DB::table('oauth_clients')->insert([
            'id'              => $clientId, 'owner_type' => null, 'owner_id' => null, 'name' => 'Limit Test App',
            'secret'          => bcrypt('test-secret'), 'provider' => null,
            'redirect_uris'   => (string) json_encode(['http://localhost:9999/callback']),
            'grant_types'     => (string) json_encode(['authorization_code']), 'revoked' => false,
            'client_logo_url' => null, 'registration_method' => 'dcr', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $html = (string) $test->actingAs($user, 'web')->get('/oauth/authorize?' . http_build_query([
        'client_id' => $clientId, 'response_type' => 'code', 'redirect_uri' => 'http://localhost:9999/callback',
        'scope'     => 'payments:write', 'state' => 'limit-state',
    ]))->assertOk()->getContent();
    preg_match('/name="auth_token"\s+value="([^"]+)"/', $html, $auth);
    preg_match('/name="_token"\s+value="([^"]+)"/', $html, $csrf);

    $form = ['_token' => $csrf[1] ?? '', 'auth_token' => $auth[1] ?? '', 'state' => 'limit-state'];
    if ($limitField !== null) {
        $form['mcp_daily_limit_minor'] = $limitField;
    }

    return [$clientId, $test->actingAs($user, 'web')->post('/oauth/authorize', $form)];
}

function consentPolicy(string $clientId, User $user): ?object
{
    return DB::table('mcp_token_policies')->where('token_id', "grant:{$clientId}:{$user->id}")->first();
}

it('saves the daily spending limit the user picks on the consent screen', function () {
    $user = User::factory()->create();

    [$clientId, $approve] = approveConsent($this, $user, '200000');

    $approve->assertStatus(302);
    $policy = consentPolicy($clientId, $user);
    expect((int) $policy?->daily_limit_minor)->toBe(200000)
        ->and((string) $policy?->daily_limit_currency)->toBe('USD');
});

it('saves "no limit" when the user picks it', function () {
    $user = User::factory()->create();

    [$clientId] = approveConsent($this, $user, '');

    expect((int) consentPolicy($clientId, $user)?->daily_limit_minor)->toBe(PHP_INT_MAX);
});

it('falls back to the default limit for a value the screen never offered', function () {
    $user = User::factory()->create();

    [$clientId] = approveConsent($this, $user, '999999999');

    expect((int) consentPolicy($clientId, $user)?->daily_limit_minor)->toBe(50000);
});

it('updates the limit on a later approval and keeps what was already spent today', function () {
    $user = User::factory()->create();
    [$clientId] = approveConsent($this, $user, '50000');
    DB::table('mcp_token_policies')->where('token_id', "grant:{$clientId}:{$user->id}")->update(['daily_spend_minor' => 1200]);

    approveConsent($this, $user, '5000', $clientId);

    expect((int) consentPolicy($clientId, $user)?->daily_limit_minor)->toBe(5000)
        ->and((int) consentPolicy($clientId, $user)?->daily_spend_minor)->toBe(1200);
});

it('saves nothing when the approval is not a valid Passport approval', function () {
    $user = User::factory()->create();
    $clientId = (string) Str::uuid();

    $this->actingAs($user, 'web')->post('/oauth/authorize', ['auth_token' => 'forged', 'state' => 'x', 'mcp_daily_limit_minor' => '1000000']);

    expect(DB::table('mcp_token_policies')->count())->toBe(0)
        ->and(consentPolicy($clientId, $user))->toBeNull();
});

/**
 * An earlier approval: a live token for the app with the requested scope, which
 * makes Passport approve again without showing the consent screen.
 */
function grantedBefore(User $user, string $clientId): void
{
    DB::table('oauth_access_tokens')->insert([
        'id'         => Str::random(80), 'user_id' => $user->id, 'client_id' => $clientId, 'name' => null,
        'scopes'     => (string) json_encode(['payments:write']), 'revoked' => false,
        'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addMonth(),
    ]);
}

/**
 * @return Illuminate\Testing\TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function authorizeAgain(PHPUnit\Framework\TestCase $test, User $user, string $clientId, ?string $prompt = null): Illuminate\Testing\TestResponse
{
    $query = ['client_id' => $clientId, 'response_type' => 'code', 'redirect_uri' => 'http://localhost:9999/callback', 'scope' => 'payments:write', 'state' => 'again'];
    if ($prompt !== null) {
        $query['prompt'] = $prompt;
    }

    return $test->actingAs($user, 'web')->get('/oauth/authorize?' . http_build_query($query));
}

it('shows the consent screen again for an approval that has no saved limit', function () {
    // Approvals made before limits were saved keep live tokens, so Passport
    // skipped the screen and spending tools failed with NO_POLICY for good.
    $user = User::factory()->create();
    [$clientId] = approveConsent($this, $user, '50000');
    DB::table('mcp_token_policies')->delete();
    grantedBefore($user, $clientId);

    authorizeAgain($this, $user, $clientId)->assertOk()->assertSee('Daily spending limit');
});

it('skips the consent screen once the approval has a saved limit', function () {
    $user = User::factory()->create();
    [$clientId] = approveConsent($this, $user, '50000');
    grantedBefore($user, $clientId);

    authorizeAgain($this, $user, $clientId)->assertRedirect();
});

it('leaves a prompt=none request alone', function () {
    $user = User::factory()->create();
    [$clientId] = approveConsent($this, $user, '50000');
    DB::table('mcp_token_policies')->delete();
    grantedBefore($user, $clientId);

    authorizeAgain($this, $user, $clientId, 'none')->assertRedirect();
});
