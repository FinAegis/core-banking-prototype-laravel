<?php

declare(strict_types=1);

use App\Domain\Auth\Exceptions\PrivyEmailOtpException;
use App\Domain\Auth\Services\PrivyEmailOtpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Mockery\MockInterface;
use Psr\Http\Message\RequestInterface;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'privy.app_id'       => 'test-app-id',
        'privy.app_secret'   => 'test-app-secret',
        'privy.api_base_url' => 'https://auth.privy.io',
    ]);
});

it('sends email + method on sendCode and includes Basic Auth + privy-app-id headers', function (): void {
    /** @var ClientInterface&MockInterface $http */
    $http = Mockery::mock(ClientInterface::class);
    /** @var Mockery\Expectation $expect */
    $expect = $http->shouldReceive('send');
    $expect->once()->andReturnUsing(function (RequestInterface $request) {
        expect($request->getMethod())->toBe('POST');
        expect((string) $request->getUri())->toBe('https://auth.privy.io/api/v1/passwordless/init');
        expect($request->getHeaderLine('privy-app-id'))->toBe('test-app-id');
        expect($request->getHeaderLine('Authorization'))->toBe('Basic ' . base64_encode('test-app-id:test-app-secret'));
        $body = json_decode((string) $request->getBody(), true);
        expect($body)->toBe(['email' => 'jane@example.com', 'method' => 'email']);

        return new PsrResponse(200, [], '{"success":true}');
    });

    $client = new PrivyEmailOtpClient($http);
    $client->sendCode('Jane@Example.COM');
});

it('returns Privy user id + linked email on loginWithCode', function (): void {
    /** @var ClientInterface&MockInterface $http */
    $http = Mockery::mock(ClientInterface::class);
    /** @var Mockery\Expectation $expect */
    $expect = $http->shouldReceive('send');
    $expect->once()->andReturn(new PsrResponse(200, [], (string) json_encode([
        'user' => [
            'id'              => 'did:privy:test123',
            'linked_accounts' => [
                ['type' => 'wallet', 'address' => '0xabc'],
                ['type' => 'email', 'address' => 'Jane@Example.COM'],
            ],
        ],
    ])));

    $client = new PrivyEmailOtpClient($http);
    $resolved = $client->loginWithCode('jane@example.com', '123456');

    expect($resolved)->toBe([
        'id'    => 'did:privy:test123',
        'email' => 'jane@example.com',
    ]);
});

it('falls back to the input email when linked_accounts has no email entry', function (): void {
    /** @var ClientInterface&MockInterface $http */
    $http = Mockery::mock(ClientInterface::class);
    /** @var Mockery\Expectation $expect */
    $expect = $http->shouldReceive('send');
    $expect->once()->andReturn(new PsrResponse(200, [], (string) json_encode([
        'user' => ['id' => 'did:privy:noemail'],
    ])));

    $client = new PrivyEmailOtpClient($http);
    $resolved = $client->loginWithCode('jane@example.com', '123456');

    expect($resolved['email'])->toBe('jane@example.com');
    expect($resolved['id'])->toBe('did:privy:noemail');
});

it('throws apiError with the upstream message on a 4xx response', function (): void {
    /** @var ClientInterface&MockInterface $http */
    $http = Mockery::mock(ClientInterface::class);
    /** @var Mockery\Expectation $expect */
    $expect = $http->shouldReceive('send');
    $expect->once()->andReturn(new PsrResponse(400, [], (string) json_encode(['error' => 'Invalid email address'])));

    $client = new PrivyEmailOtpClient($http);
    expect(fn () => $client->sendCode('bad'))->toThrow(PrivyEmailOtpException::class, 'Invalid email address');
});

it('throws malformedResponse when login response has no user.id', function (): void {
    /** @var ClientInterface&MockInterface $http */
    $http = Mockery::mock(ClientInterface::class);
    /** @var Mockery\Expectation $expect */
    $expect = $http->shouldReceive('send');
    $expect->once()->andReturn(new PsrResponse(200, [], (string) json_encode(['user' => []])));

    $client = new PrivyEmailOtpClient($http);
    expect(fn () => $client->loginWithCode('jane@example.com', '123456'))
        ->toThrow(PrivyEmailOtpException::class, 'missing user.id');
});

it('throws misconfigured when app_id or app_secret is empty', function (): void {
    config(['privy.app_id' => '', 'privy.app_secret' => '']);

    /** @var ClientInterface&MockInterface $http */
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldReceive('send')->never();

    $client = new PrivyEmailOtpClient($http);
    expect(fn () => $client->sendCode('jane@example.com'))->toThrow(PrivyEmailOtpException::class, 'credentials');
});
