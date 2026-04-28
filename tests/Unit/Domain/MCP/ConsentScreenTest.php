<?php

declare(strict_types=1);

use App\Domain\MCP\Auth\ConsentScreenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array{0: string, 1: Laravel\Passport\Client}
 */
function seedConsentClient(string $name, ?string $logoUrl = null): array
{
    $clientId = (string) Str::uuid();
    DB::table('oauth_clients')->insert([
        'id'                  => $clientId,
        'owner_type'          => null,
        'owner_id'            => null,
        'name'                => $name,
        'secret'              => 'irrelevant',
        'provider'            => null,
        'redirect_uris'       => (string) json_encode(['http://localhost:1234/cb']),
        'grant_types'         => (string) json_encode(['authorization_code']),
        'revoked'             => false,
        'client_logo_url'     => $logoUrl,
        'registration_method' => 'dcr',
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    /** @var Laravel\Passport\Client $client */
    $client = Laravel\Passport\Client::query()->findOrFail($clientId);

    return [$clientId, $client];
}

it('renders the consent view with scope rows and write/read distinction', function () {
    [$clientId] = seedConsentClient('Acme MCP App', 'https://example.com/logo.png');

    $request = Request::create('/oauth/authorize', 'GET', [
        'client_id' => $clientId,
        'scope'     => 'accounts:read payments:write',
        'state'     => 'xyz123',
    ]);
    $request->setLaravelSession(app('session.store'));

    $response = (new ConsentScreenController())($request);

    expect($response)->toBeInstanceOf(View::class);

    /** @var array<string, mixed> $data */
    $data = $response->getData();

    expect($data)->toHaveKeys([
        'client', 'scopes', 'authorize_url', 'deny_url',
        'spending_options', 'default_limit_minor', 'currency',
        'state', 'auth_token',
    ]);

    expect($data['client']->name)->toBe('Acme MCP App');
    expect($data['authorize_url'])->toBeString()->not->toBe('');
    expect($data['deny_url'])->toBeString()->not->toBe('');
    expect($data['state'])->toBe('xyz123');

    // Scope rows: ordered as provided, descriptions resolved from config('mcp.scopes')
    expect($data['scopes'])->toHaveCount(2);

    $accountsRead = $data['scopes'][0];
    expect($accountsRead['id'])->toBe('accounts:read');
    expect($accountsRead['is_write'])->toBeFalse();
    expect($accountsRead['description'])->toBe(config('mcp.scopes.accounts:read'));

    $paymentsWrite = $data['scopes'][1];
    expect($paymentsWrite['id'])->toBe('payments:write');
    expect($paymentsWrite['is_write'])->toBeTrue();
    expect($paymentsWrite['description'])->toBe(config('mcp.scopes.payments:write'));
});

it('marks sms:send as a write-style scope', function () {
    [$clientId] = seedConsentClient('SMS App');

    $request = Request::create('/oauth/authorize', 'GET', [
        'client_id' => $clientId,
        'scope'     => 'sms:send',
    ]);
    $request->setLaravelSession(app('session.store'));

    $response = (new ConsentScreenController())($request);

    /** @var array<string, mixed> $data */
    $data = $response->getData();

    expect($data['scopes'][0]['id'])->toBe('sms:send');
    expect($data['scopes'][0]['is_write'])->toBeTrue();
});

it('aborts with 404 when the client_id is unknown', function () {
    $request = Request::create('/oauth/authorize', 'GET', [
        'client_id' => '00000000-0000-4000-8000-000000000000',
        'scope'     => 'accounts:read',
    ]);
    $request->setLaravelSession(app('session.store'));

    expect(fn () => (new ConsentScreenController())($request))
        ->toThrow(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
});
