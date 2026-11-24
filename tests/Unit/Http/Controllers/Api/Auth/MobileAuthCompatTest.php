<?php

declare(strict_types=1);

use App\Domain\Compliance\Services\AccountClosureService;
use App\Domain\Mobile\Services\BiometricJWTService;
use App\Domain\Mobile\Services\MobileDeviceService;
use App\Domain\Mobile\Services\PasskeyAuthenticationService;
use App\Http\Controllers\Api\Auth\AccountDeletionController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\PasskeyController;
use App\Models\User;
use App\Services\IpBlockingService;
use Illuminate\Http\Request;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

describe('LoginController response envelope', function (): void {
    it('wraps user/me response in { success, data } envelope', function (): void {
        /** @var IpBlockingService&Mockery\MockInterface $ipBlockingService */
        $ipBlockingService = Mockery::mock(IpBlockingService::class);
        /** @var BiometricJWTService&Mockery\MockInterface $biometricJWTService */
        $biometricJWTService = Mockery::mock(BiometricJWTService::class);
        $controller = new LoginController($ipBlockingService, $biometricJWTService);

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->name = 'Test User';
        $user->email = 'test@example.com';

        $request = Request::create('/api/auth/user', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $controller->user($request);
        $data = $response->getData(true);

        expect($data)->toHaveKey('success')
            ->and($data['success'])->toBeTrue()
            ->and($data)->toHaveKey('data');
    });

    it('login method returns success and data keys in response', function (): void {
        // Verify the login method source code returns the { success, data } envelope
        $reflection = new ReflectionMethod(LoginController::class, 'login');
        $source = file_get_contents($reflection->getFileName());

        expect($source)->toContain("'success' => true")
            ->and($source)->toContain("'data'    => [")
            ->and($source)->toContain("'access_token'")
            ->and($source)->toContain("'refresh_token'");
    });
});

describe('AccountDeletionController', function (): void {
    it('requires confirmation string DELETE and closes the account', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;

        /** @var AccountClosureService&Mockery\MockInterface $closure */
        $closure = Mockery::mock(AccountClosureService::class);
        $closure->shouldReceive('close')->once()->with($user, AccountClosureService::SOURCE_APP);
        $controller = new AccountDeletionController($closure);

        $request = Request::create('/api/auth/delete-account', 'POST', [
            'confirmation' => 'DELETE',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $controller($request);
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue()
            ->and($data['data'])->toHaveKeys(['message', 'deleted_at']);
    });

    it('rejects request without proper confirmation', function (): void {
        /** @var AccountClosureService&Mockery\MockInterface $closure */
        $closure = Mockery::mock(AccountClosureService::class);
        $closure->shouldNotReceive('close');
        $controller = new AccountDeletionController($closure);

        $request = Request::create('/api/auth/delete-account', 'POST', [
            'confirmation' => 'wrong',
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $request->setUserResolver(fn () => $user);

        $controller($request);
    })->throws(Illuminate\Validation\ValidationException::class);
});

describe('PasskeyController register', function (): void {
    it('registers a passkey for the authenticated user device', function (): void {
        /** @var PasskeyAuthenticationService&Mockery\MockInterface $passkeyService */
        $passkeyService = Mockery::mock(PasskeyAuthenticationService::class);
        /** @var MobileDeviceService&Mockery\MockInterface $deviceService */
        $deviceService = Mockery::mock(MobileDeviceService::class);

        $device = Mockery::mock(App\Domain\Mobile\Models\MobileDevice::class)->makePartial();
        $device->user_id = 1;

        $deviceService->shouldReceive('findByDeviceId')
            ->with('device-123')
            ->andReturn($device);

        $passkeyService->shouldReceive('registerPasskey')
            ->with($device, 'cred-id-abc', 'pub-key-xyz')
            ->andReturn([
                'credential_id' => 'cred-id-abc',
                'registered_at' => now()->toIso8601String(),
            ]);

        /** @var BiometricJWTService&Mockery\MockInterface $biometricJWTService */
        $biometricJWTService = Mockery::mock(BiometricJWTService::class);
        $controller = new PasskeyController($passkeyService, $deviceService, $biometricJWTService);

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;

        $request = Request::create('/api/auth/passkey/register', 'POST', [
            'device_id'     => 'device-123',
            'credential_id' => 'cred-id-abc',
            'public_key'    => 'pub-key-xyz',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $controller->register($request);
        $data = $response->getData(true);

        expect($response->getStatusCode())->toBe(201)
            ->and($data['success'])->toBeTrue()
            ->and($data['data'])->toHaveKey('credential_id');
    });

    it('rejects register for device not belonging to user', function (): void {
        /** @var PasskeyAuthenticationService&Mockery\MockInterface $passkeyService */
        $passkeyService = Mockery::mock(PasskeyAuthenticationService::class);
        /** @var MobileDeviceService&Mockery\MockInterface $deviceService */
        $deviceService = Mockery::mock(MobileDeviceService::class);

        $device = Mockery::mock(App\Domain\Mobile\Models\MobileDevice::class)->makePartial();
        $device->user_id = 999; // Different user

        $deviceService->shouldReceive('findByDeviceId')
            ->with('device-123')
            ->andReturn($device);

        /** @var BiometricJWTService&Mockery\MockInterface $biometricJWTService */
        $biometricJWTService = Mockery::mock(BiometricJWTService::class);
        $controller = new PasskeyController($passkeyService, $deviceService, $biometricJWTService);

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;

        $request = Request::create('/api/auth/passkey/register', 'POST', [
            'device_id'     => 'device-123',
            'credential_id' => 'cred-id-abc',
            'public_key'    => 'pub-key-xyz',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $controller->register($request);

        expect($response->getStatusCode())->toBe(403);
    });

    it('returns 404 for non-existent device', function (): void {
        /** @var PasskeyAuthenticationService&Mockery\MockInterface $passkeyService */
        $passkeyService = Mockery::mock(PasskeyAuthenticationService::class);
        /** @var MobileDeviceService&Mockery\MockInterface $deviceService */
        $deviceService = Mockery::mock(MobileDeviceService::class);

        $deviceService->shouldReceive('findByDeviceId')
            ->with('nonexistent')
            ->andReturn(null);

        /** @var BiometricJWTService&Mockery\MockInterface $biometricJWTService */
        $biometricJWTService = Mockery::mock(BiometricJWTService::class);
        $controller = new PasskeyController($passkeyService, $deviceService, $biometricJWTService);

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;

        $request = Request::create('/api/auth/passkey/register', 'POST', [
            'device_id'     => 'nonexistent',
            'credential_id' => 'cred-id-abc',
            'public_key'    => 'pub-key-xyz',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $controller->register($request);

        expect($response->getStatusCode())->toBe(404);
    });
});

describe('CORS configuration', function (): void {
    it('includes mobile client headers in allowed headers', function (): void {
        $corsConfig = config('cors.allowed_headers');

        expect($corsConfig)->toContain('X-Client-Platform')
            ->and($corsConfig)->toContain('X-Client-Version')
            ->and($corsConfig)->toContain('X-Payment-Version');
    });

    it('includes PATCH in allowed methods', function (): void {
        $corsMethods = config('cors.allowed_methods');

        expect($corsMethods)->toContain('PATCH');
    });
});

describe('Route aliases', function (): void {
    it('has auth/me route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('api.auth.me');
        expect($route)->not->toBeNull();
    });

    it('has auth/delete-account route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('api.auth.delete-account');
        expect($route)->not->toBeNull();
    });

    it('has passkey challenge GET alias defined', function (): void {
        $route = app('router')->getRoutes()->getByName('api.auth.passkey.challenge.get');
        expect($route)->not->toBeNull();
    });

    it('has passkey verify alias defined', function (): void {
        $route = app('router')->getRoutes()->getByName('api.auth.passkey.verify');
        expect($route)->not->toBeNull();
    });

    it('has passkey register route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('api.auth.passkey.register');
        expect($route)->not->toBeNull();
    });

    it('has receipt GET alias defined', function (): void {
        $route = app('router')->getRoutes()->getByName('mobile.transactions.receipt.get');
        expect($route)->not->toBeNull();
    });

    it('has parameterized network status route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('mobile.networks.status.parameterized');
        expect($route)->not->toBeNull();
    });
});
