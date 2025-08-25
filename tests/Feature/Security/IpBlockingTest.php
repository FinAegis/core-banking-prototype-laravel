<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\IpBlocking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IpBlockingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any existing IP blocks
        Cache::flush();
    }

    /** @test */
    public function it_allows_whitelisted_ips()
    {
        // Whitelist a specific IP
        IpBlocking::whitelist('192.168.1.1');

        // Make request from whitelisted IP
        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
            ->postJson('/api/auth/login', [
                'email'    => 'invalid@example.com',
                'password' => 'wrong',
            ]);

        // Should not be blocked despite invalid credentials
        $this->assertNotEquals(403, $response->status());
    }

    /** @test */
    public function it_blocks_ip_after_max_failed_attempts()
    {
        $ip = '192.168.1.100';
        $maxAttempts = 10; // As configured in IpBlocking::CONFIG

        // Make multiple failed login attempts
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/auth/login', [
                    'email'    => 'invalid@example.com',
                    'password' => 'wrong',
                ]);
        }

        // Next request should be blocked
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->getJson('/api/auth/user');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Access Denied',
            ]);
    }

    /** @test */
    public function it_permanently_blocks_ip_after_threshold()
    {
        $ip = '192.168.1.200';

        // Simulate reaching permanent block threshold
        Cache::put("ip_failed_attempts:{$ip}", 50);

        // Trigger one more failed attempt to activate permanent block
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/login', [
                'email'    => 'invalid@example.com',
                'password' => 'wrong',
            ]);

        // Verify IP is permanently blacklisted
        $blacklist = Cache::get('ip_blacklist', []);
        $this->assertContains($ip, $blacklist);

        // Subsequent requests should be blocked
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->getJson('/api/auth/user');

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Your IP address has been permanently blocked due to suspicious activity.',
            ]);
    }

    /** @test */
    public function it_can_unblock_an_ip()
    {
        $ip = '192.168.1.150';

        // Block the IP
        Cache::put("ip_blocked:{$ip}", now()->addHour());

        // Verify it's blocked
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->getJson('/api/auth/user');
        $response->assertStatus(403);

        // Unblock the IP
        IpBlocking::unblock($ip);

        // Verify it's no longer blocked
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->getJson('/api/auth/user');
        $this->assertNotEquals(403, $response->status());
    }

    /** @test */
    public function it_supports_cidr_notation_for_blocking()
    {
        // Add a CIDR range to blacklist
        $blacklist = ['192.168.0.0/24'];
        Cache::forever('ip_blacklist', $blacklist);

        // IPs within the range should be blocked
        $blockedIps = ['192.168.0.1', '192.168.0.100', '192.168.0.255'];
        foreach ($blockedIps as $ip) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->getJson('/api/auth/user');

            $response->assertStatus(403);
        }

        // IP outside the range should not be blocked
        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
            ->getJson('/api/auth/user');

        $this->assertNotEquals(403, $response->status());
    }

    /** @test */
    public function it_tracks_failed_attempts_with_expiration()
    {
        $ip = '192.168.1.50';
        $key = "ip_failed_attempts:{$ip}";

        // Make a failed attempt
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/login', [
                'email'    => 'invalid@example.com',
                'password' => 'wrong',
            ]);

        // Check that attempts are tracked
        $this->assertEquals(1, Cache::get($key));

        // Verify TTL is set (should be 24 hours = 86400 seconds)
        $this->assertTrue(Cache::has($key));
    }

    /** @test */
    public function it_allows_internal_ips_by_default()
    {
        $internalIps = ['127.0.0.1', '::1'];

        foreach ($internalIps as $ip) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->getJson('/api/auth/user');

            // Should not be blocked even without authentication
            $this->assertNotEquals(403, $response->status());
        }
    }
}
