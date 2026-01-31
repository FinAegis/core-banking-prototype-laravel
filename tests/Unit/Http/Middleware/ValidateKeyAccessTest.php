<?php

namespace Tests\Unit\Http\Middleware;

use App\Domain\Wallet\Models\KeyAccessLog;
use App\Http\Middleware\ValidateKeyAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ValidateKeyAccessTest extends TestCase
{
    private ValidateKeyAccess $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ValidateKeyAccess();
    }

    public function test_unauthenticated_user_receives_401(): void
    {
        // Arrange
        $request = Request::create('/api/wallet/keys', 'GET');
        Auth::shouldReceive('user')->andReturn(null);

        // Act
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals(['error' => 'Unauthorized'], json_decode((string) $response->getContent(), true));
    }

    public function test_user_without_permission_receives_403(): void
    {
        // Arrange
        $user = $this->createMock(User::class);
        $user->method('can')->with('access_keys')->willReturn(false);

        $request = Request::create('/api/wallet/keys', 'GET');
        Auth::shouldReceive('user')->andReturn($user);

        // Act
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(['error' => 'Forbidden - Insufficient permissions'], json_decode((string) $response->getContent(), true));
    }

    public function test_user_with_permission_passes_through(): void
    {
        // Arrange
        $user = $this->createMock(User::class);
        $user->method('can')->with('access_keys')->willReturn(true);
        $user->id = 123;

        $request = Request::create('/api/wallet/keys', 'GET');
        Auth::shouldReceive('user')->andReturn($user);

        // Act
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['success' => true], json_decode((string) $response->getContent(), true));
    }

    public function test_rate_limiting_blocks_excessive_requests(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->id = 123;

        // Grant permission to the user
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access_keys']);
        $user->givePermissionTo($permission);

        $request = Request::create('/api/wallet/keys', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        $request->headers->set('User-Agent', 'Test Browser');

        Auth::shouldReceive('user')->andReturn($user);

        // Simulate rate limit exceeded
        RateLimiter::shouldReceive('tooManyAttempts')
            ->with('key-access:123', 10)
            ->andReturn(true);

        RateLimiter::shouldReceive('availableIn')
            ->with('key-access:123')
            ->andReturn(60);

        // Act
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(429, $response->getStatusCode());
        $responseData = json_decode((string) $response->getContent(), true);
        $this->assertEquals('Too many key access attempts', $responseData['error']);
        $this->assertEquals(60, $responseData['retry_after']);

        // Verify rate limit log was created
        $log = KeyAccessLog::where('action', 'rate_limited')->first();
        $this->assertNotNull($log);
        $this->assertEquals(123, $log->user_id);
        $this->assertEquals('192.168.1.100', $log->ip_address);
        $this->assertEquals('Test Browser', $log->user_agent);
        $this->assertEquals(10, $log->metadata['attempts']);
        $this->assertEquals(60, $log->metadata['retry_after']);
    }

    public function test_suspicious_activity_detection_for_high_frequency_access(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->id = 123;

        // Grant permission to the user
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access_keys']);
        $user->givePermissionTo($permission);

        // Create many recent access logs
        for ($i = 0; $i < 25; $i++) {
            KeyAccessLog::create([
                'wallet_id'   => 'wallet-' . $i,
                'user_id'     => $user->id,
                'action'      => 'retrieve',
                'ip_address'  => '192.168.1.100',
                'user_agent'  => 'Test Browser',
                'metadata'    => [],
                'accessed_at' => now()->subMinutes(2),
            ]);
        }

        $request = Request::create('/api/wallet/keys', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        $request->headers->set('User-Agent', 'Test Browser');

        Auth::shouldReceive('user')->andReturn($user);

        RateLimiter::shouldReceive('tooManyAttempts')->andReturn(false);
        RateLimiter::shouldReceive('hit')->once();

        // Act
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        // Verify suspicious activity log was created
        $log = KeyAccessLog::where('action', 'suspicious_pattern')
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals('192.168.1.100', $log->ip_address);
        $this->assertEquals(25, $log->metadata['recent_accesses']);
        $this->assertEquals(20, $log->metadata['threshold']);
        $this->assertEquals('5 minutes', $log->metadata['time_window']);
    }

    public function test_ip_change_detection(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->id = 123;

        // Grant permission to the user
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access_keys']);
        $user->givePermissionTo($permission);

        // Create previous access from different IP
        KeyAccessLog::create([
            'wallet_id'   => 'wallet-123',
            'user_id'     => $user->id,
            'action'      => 'retrieve',
            'ip_address'  => '192.168.1.100',
            'user_agent'  => 'Test Browser',
            'metadata'    => [],
            'accessed_at' => now()->subMinutes(10),
        ]);

        $request = Request::create('/api/wallet/keys', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.200'); // Different IP
        $request->headers->set('User-Agent', 'Test Browser');

        Auth::shouldReceive('user')->andReturn($user);

        RateLimiter::shouldReceive('tooManyAttempts')->andReturn(false);
        RateLimiter::shouldReceive('hit')->once();

        // Act
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        // Verify IP change log was created
        $log = KeyAccessLog::where('action', 'ip_changed')
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals('192.168.1.200', $log->ip_address);
        $this->assertEquals('192.168.1.100', $log->metadata['previous_ip']);
        $this->assertEquals('192.168.1.200', $log->metadata['new_ip']);
    }

    public function test_custom_permission_parameter(): void
    {
        // Arrange
        $user = $this->createMock(User::class);
        $user->method('can')->with('manage_wallets')->willReturn(true);
        $user->id = 123;

        $request = Request::create('/api/wallet/keys', 'GET');
        Auth::shouldReceive('user')->andReturn($user);

        // Act
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        }, 'manage_wallets');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_no_suspicious_activity_for_normal_usage(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->id = 123;

        // Grant permission to the user
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access_keys']);
        $user->givePermissionTo($permission);

        // Create only a few recent access logs (below threshold)
        for ($i = 0; $i < 5; $i++) {
            KeyAccessLog::create([
                'wallet_id'   => 'wallet-' . $i,
                'user_id'     => $user->id,
                'action'      => 'retrieve',
                'ip_address'  => '192.168.1.100',
                'user_agent'  => 'Test Browser',
                'metadata'    => [],
                'accessed_at' => now()->subMinutes(2),
            ]);
        }

        $request = Request::create('/api/wallet/keys', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        $request->headers->set('User-Agent', 'Test Browser');

        Auth::shouldReceive('user')->andReturn($user);

        RateLimiter::shouldReceive('tooManyAttempts')->andReturn(false);
        RateLimiter::shouldReceive('hit')->once();

        // Act
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        // Verify no suspicious activity log was created
        $log = KeyAccessLog::where('action', 'suspicious_pattern')
            ->where('user_id', $user->id)
            ->first();
        $this->assertNull($log);
    }
}
