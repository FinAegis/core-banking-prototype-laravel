<?php

declare(strict_types=1);

use App\Domain\MCP\Auth\McpOAuthGuard;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Token;
use Tests\TestCase;

uses(TestCase::class);

it('returns 401 with WWW-Authenticate Bearer + resource_metadata header when no token', function () {
    $guard = new McpOAuthGuard();
    $request = Request::create('/mcp', 'POST');
    $response = $guard->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(401);
    $header = (string) $response->headers->get('WWW-Authenticate');
    expect($header)->toContain('Bearer');
    expect($header)->toContain('resource_metadata=');
    expect($header)->toContain('/.well-known/oauth-protected-resource');
});

it('returns 401 with JSON-RPC error envelope when no token', function () {
    $guard = new McpOAuthGuard();
    $request = Request::create('/mcp', 'POST');
    $response = $guard->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(401);
    expect($response)->toBeInstanceOf(Illuminate\Http\JsonResponse::class);
    assert($response instanceof Illuminate\Http\JsonResponse);
    /** @var array<string, mixed> $body */
    $body = (array) $response->getData(true);
    expect($body['jsonrpc'] ?? null)->toBe('2.0');
    /** @var array<string, mixed> $error */
    $error = (array) ($body['error'] ?? []);
    expect($error['code'] ?? null)->toBe(-32001);
    expect($error['message'] ?? null)->toBe('UNAUTHENTICATED');
    expect(array_key_exists('id', $body))->toBeTrue();
    expect($body['id'])->toBeNull();
});

it('returns 401 when bearer is present but Passport guard cannot resolve a user', function () {
    /** @var Guard&Mockery\MockInterface $apiGuard */
    $apiGuard = Mockery::mock(Guard::class);
    $apiGuard->shouldReceive('user')->andReturn(null);
    Auth::shouldReceive('guard')->with('api')->andReturn($apiGuard);

    $guard = new McpOAuthGuard();
    $request = Request::create('/mcp', 'POST');
    $request->headers->set('Authorization', 'Bearer not-a-real-token');

    $response = $guard->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(401);
    $header = (string) $response->headers->get('WWW-Authenticate');
    expect($header)->toContain('Bearer');
    expect($header)->toContain('resource_metadata=');
});

it('returns 401 when the resolved Passport token is revoked', function () {
    $token = new Token();
    $token->revoked = true;
    $token->scopes = ['accounts:read'];

    $user = Mockery::mock();
    $user->shouldReceive('token')->andReturn($token);

    /** @var Guard&Mockery\MockInterface $apiGuard */
    $apiGuard = Mockery::mock(Guard::class);
    $apiGuard->shouldReceive('user')->andReturn($user);
    Auth::shouldReceive('guard')->with('api')->andReturn($apiGuard);
    // Once a user is resolved, the guard switches the default to `api` so
    // existing tools' Auth::user() calls find the bearer-token user.
    Auth::shouldReceive('shouldUse')->with('api');

    $guard = new McpOAuthGuard();
    $request = Request::create('/mcp', 'POST');
    $request->headers->set('Authorization', 'Bearer revoked-token');

    $response = $guard->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(401);
});

it('lets the request through with a valid token and attaches the token to the request', function () {
    $token = new Token();
    $token->revoked = false;
    $token->scopes = ['accounts:read', 'payments:write'];

    $user = Mockery::mock();
    $user->shouldReceive('token')->andReturn($token);

    /** @var Guard&Mockery\MockInterface $apiGuard */
    $apiGuard = Mockery::mock(Guard::class);
    $apiGuard->shouldReceive('user')->andReturn($user);
    Auth::shouldReceive('guard')->with('api')->andReturn($apiGuard);
    // Once a user is resolved, the guard switches the default to `api` so
    // existing tools' Auth::user() calls find the bearer-token user.
    Auth::shouldReceive('shouldUse')->with('api');

    $guard = new McpOAuthGuard();
    $request = Request::create('/mcp', 'POST');
    $request->headers->set('Authorization', 'Bearer valid-token');

    $response = $guard->handle($request, fn (Request $r) => response()->json([
        'token_attached' => $r->attributes->get('mcp.token') instanceof Token,
    ]));

    expect($response->getStatusCode())->toBe(200);
    /** @var array<string, mixed> $payload */
    $payload = (array) ($response instanceof Illuminate\Http\JsonResponse ? $response->getData(true) : []);
    expect($payload['token_attached'] ?? false)->toBeTrue();
    expect($request->attributes->get('mcp.token'))->toBe($token);
});

it('lets the request through when a required scope is satisfied by the token', function () {
    $token = new Token();
    $token->revoked = false;
    $token->scopes = ['accounts:read', 'payments:write'];

    $user = Mockery::mock();
    $user->shouldReceive('token')->andReturn($token);

    /** @var Guard&Mockery\MockInterface $apiGuard */
    $apiGuard = Mockery::mock(Guard::class);
    $apiGuard->shouldReceive('user')->andReturn($user);
    Auth::shouldReceive('guard')->with('api')->andReturn($apiGuard);
    // Once a user is resolved, the guard switches the default to `api` so
    // existing tools' Auth::user() calls find the bearer-token user.
    Auth::shouldReceive('shouldUse')->with('api');

    $guard = new McpOAuthGuard();
    $request = Request::create('/mcp', 'POST');
    $request->headers->set('Authorization', 'Bearer scoped-token');

    $response = $guard->handle($request, fn () => response('OK'), 'payments:write');

    expect($response->getStatusCode())->toBe(200);
    expect($request->attributes->get('mcp.token'))->toBe($token);
});

it('strips quote-break characters from the WWW-Authenticate resource_metadata URL', function () {
    config(['mcp.resource_uri' => "https://mcp.zelta.app\"\r\n attacker"]);

    $guard = new McpOAuthGuard();
    $response = $guard->handle(Request::create('/mcp', 'POST'), fn () => response('OK'));

    $header = (string) $response->headers->get('WWW-Authenticate');
    expect($header)->not->toContain('"attacker');
    expect($header)->not->toContain("\r");
    expect($header)->not->toContain("\n");
    expect($header)->toContain('Bearer resource_metadata="https://mcp.zelta.app');
});

it('returns 403 with INSUFFICIENT_SCOPE when the token lacks the required scope', function () {
    $token = new Token();
    $token->revoked = false;
    $token->scopes = ['accounts:read'];

    $user = Mockery::mock();
    $user->shouldReceive('token')->andReturn($token);

    /** @var Guard&Mockery\MockInterface $apiGuard */
    $apiGuard = Mockery::mock(Guard::class);
    $apiGuard->shouldReceive('user')->andReturn($user);
    Auth::shouldReceive('guard')->with('api')->andReturn($apiGuard);
    // Once a user is resolved, the guard switches the default to `api` so
    // existing tools' Auth::user() calls find the bearer-token user.
    Auth::shouldReceive('shouldUse')->with('api');

    $guard = new McpOAuthGuard();
    $request = Request::create('/mcp', 'POST');
    $request->headers->set('Authorization', 'Bearer scoped-token');

    $response = $guard->handle($request, fn () => response('OK'), 'payments:write');

    expect($response->getStatusCode())->toBe(403);

    /** @var array<string, mixed> $body */
    $body = (array) (method_exists($response, 'getData') ? $response->getData(true) : []);
    expect($body['jsonrpc'] ?? null)->toBe('2.0');
    /** @var array<string, mixed> $error */
    $error = (array) ($body['error'] ?? []);
    expect($error['code'] ?? null)->toBe(-32000);
    expect($error['message'] ?? null)->toBe('INSUFFICIENT_SCOPE');
    /** @var array<string, mixed> $data */
    $data = (array) ($error['data'] ?? []);
    expect($data['required'] ?? null)->toBe('payments:write');
    expect($data['granted'] ?? null)->toBe(['accounts:read']);
    expect(array_key_exists('id', $body))->toBeTrue();
    expect($body['id'])->toBeNull();
});
