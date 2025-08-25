<?php

namespace Tests\Security;

use App\Domain\Account\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('security')]
#[Group('memory-intensive')]
class ComprehensiveSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test SQL injection prevention in various endpoints.
     */
    #[Test]
    public function test_sql_injection_prevention()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create test data first
        Account::factory()->create(['id' => 1, 'user_uuid' => $user->uuid]);

        // Create a test account for search tests
        Account::factory()->create([
            'user_uuid' => $user->uuid,
            'name'      => 'Test Account',
        ]);

        $injectionPayloads = [
            "'; DROP TABLE accounts; --",
            "1' OR '1'='1",
            "admin'--",
            '1; DELETE FROM users WHERE 1=1; --',
            "' UNION SELECT * FROM users; --",
        ];

        foreach ($injectionPayloads as $payload) {
            // Test in account creation
            $response = $this->postJson('/api/accounts', [
                'name' => $payload,
                'type' => 'savings',
            ]);

            $response->assertStatus(422); // Should fail validation

            // Test in search parameters
            $response = $this->getJson("/api/accounts?search={$payload}");
            $response->assertSuccessful(); // Should handle safely

            // Verify tables still exist and data wasn't deleted
            $this->assertDatabaseHas('accounts', ['name' => 'Test Account']);
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }
    }

    /**
     * Test XSS prevention in API responses.
     */
    #[Test]
    public function test_xss_prevention()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<iframe src="javascript:alert(\'XSS\')"></iframe>',
            '"><script>alert(String.fromCharCode(88,83,83))</script>',
            '<svg onload=alert("XSS")>',
        ];

        foreach ($xssPayloads as $payload) {
            $account = Account::factory()->create([
                'name'      => $payload,
                'user_uuid' => $user->uuid,
            ]);

            $response = $this->getJson("/api/accounts/{$account->uuid}");
            $response->assertSuccessful();

            // Verify proper content type for JSON API
            $response->assertHeader('Content-Type', 'application/json');
            $response->assertHeader('X-Content-Type-Options', 'nosniff');

            // Verify JSON structure contains the raw data
            // In a JSON API, data should be returned as-is
            // XSS prevention happens on the client side when rendering
            $data = $response->json('data');
            $this->assertEquals($payload, $data['name']);

            // Ensure no HTML is being rendered
            $response->assertDontSee('<html', false);
            $response->assertDontSee('</body>', false);
        }
    }

    /**
     * Test authentication security.
     */
    #[Test]
    public function test_authentication_security()
    {
        // Enable rate limiting for tests
        config(['rate_limiting.force_in_tests' => true]);

        $user = User::factory()->create([
            'password' => Hash::make('SecurePassword123!'),
        ]);

        // Clear any existing rate limit cache
        \Cache::flush();

        // Test brute force protection
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'    => $user->email,
                'password' => 'WrongPassword',
            ]);

            if ($i < 5) {
                $response->assertStatus(422); // Invalid credentials return 422 in Laravel
            } else {
                $response->assertStatus(429); // Too many attempts
            }
        }

        // Test timing attack prevention
        $validTime = $this->timeRequest(function () use ($user) {
            return $this->postJson('/api/auth/login', [
                'email'    => $user->email,
                'password' => 'SecurePassword123!',
            ]);
        });

        $invalidTime = $this->timeRequest(function () {
            return $this->postJson('/api/auth/login', [
                'email'    => 'nonexistent@example.com',
                'password' => 'WrongPassword',
            ]);
        });

        // Times should be similar to prevent user enumeration
        $this->assertLessThan(100, abs($validTime - $invalidTime)); // Within 100ms
    }

    /**
     * Test CSRF protection.
     */
    #[Test]
    public function test_csrf_protection()
    {
        $user = User::factory()->create();

        // Test without CSRF token
        $response = $this->post('/web/accounts', [
            'name' => 'Test Account',
        ]);

        // In testing environment, CSRF might be disabled or return different status
        $this->assertContains($response->status(), [419, 302, 403]); // CSRF token mismatch or redirect

        // Test with valid CSRF token
        $this->actingAs($user);
        $response = $this->post('/web/accounts', [
            '_token' => csrf_token(),
            'name'   => 'Test Account',
        ]);

        $response->assertSuccessful();
    }

    /**
     * Test API rate limiting.
     */
    #[Test]
    public function test_api_rate_limiting()
    {
        // Enable rate limiting for tests
        config(['rate_limiting.force_in_tests' => true]);

        $user = User::factory()->create();
        $this->actingAs($user);

        // Clear all cache including rate limit keys
        \Cache::flush();

        // Test rate limit (100 requests per minute for query endpoints)
        for ($i = 0; $i < 101; $i++) {
            $response = $this->getJson('/api/accounts');

            if ($i < 100) {
                $response->assertSuccessful();
            } else {
                $response->assertStatus(429);
                $response->assertHeader('X-RateLimit-Limit', '100');
                $response->assertHeader('X-RateLimit-Remaining', '0');
            }
        }
    }

    /**
     * Test secure headers.
     */
    #[Test]
    public function test_security_headers()
    {
        $response = $this->get('/');

        // Check security headers
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Check HSTS for HTTPS
        if (app()->environment('production')) {
            $response->assertHeader('Strict-Transport-Security');
        }
    }

    /**
     * Test input validation.
     */
    #[Test]
    public function test_input_validation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Test oversized input
        $oversizedData = str_repeat('a', 10001); // 10KB+ string

        $response = $this->postJson('/api/accounts', [
            'name' => $oversizedData,
        ]);

        $response->assertStatus(422);

        // Test invalid data types
        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => ['array', 'not', 'string'],
            'to_account_uuid'   => true, // Boolean instead of string
            'amount'            => 'not a number',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from_account_uuid', 'to_account_uuid', 'amount']);
    }

    /**
     * Test secure password requirements.
     */
    #[Test]
    public function test_password_security()
    {
        $weakPasswords = [
            'password',
            '12345678',
            'qwerty123',
            'admin123',
            'Password1', // No special char
        ];

        foreach ($weakPasswords as $password) {
            $response = $this->postJson('/api/register', [
                'name'                  => 'Test User',
                'email'                 => Str::random() . '@example.com',
                'password'              => $password,
                'password_confirmation' => $password,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['password']);
        }

        // Test strong password
        $response = $this->postJson('/api/register', [
            'name'                  => 'Test User',
            'email'                 => Str::random() . '@example.com',
            'password'              => 'TestP@ssw0rd2024$Complex!UniqueString',
            'password_confirmation' => 'TestP@ssw0rd2024$Complex!UniqueString',
        ]);

        $response->assertSuccessful();
    }

    /**
     * Test file upload security.
     */
    #[Test]
    public function test_file_upload_security()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Test malicious file types
        $maliciousFiles = [
            'test.php',
            'test.exe',
            'test.sh',
            'test.bat',
        ];

        foreach ($maliciousFiles as $filename) {
            $response = $this->postJson('/api/kyc/documents', [
                'document' => \Illuminate\Http\UploadedFile::fake()->create($filename, 100),
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['document']);
        }

        // Test allowed file types
        $user = User::factory()->create();
        $this->actingAs($user);

        // Ensure storage disk is set up for testing
        \Storage::fake('private');

        $response = $this->postJson('/api/kyc/documents', [
            'document' => \Illuminate\Http\UploadedFile::fake()->image('passport.jpg', 800, 600),
            'type'     => 'passport',
        ]);

        $response->assertSuccessful();
    }

    /**
     * Test session security.
     */
    #[Test]
    public function test_session_security()
    {
        $user = User::factory()->create();

        // Login and get session
        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertSuccessful();
        $token = $response->json('access_token');
        $this->assertNotNull($token, 'Login should return an access token');

        // Test session timeout (configured to 60 minutes in sanctum config)
        $this->travel(61)->minutes();

        $response = $this->withToken($token)->getJson('/api/auth/user');
        $response->assertStatus(401); // Session expired

        // Go back to present time
        $this->travelBack();

        // Test concurrent session limit
        $tokens = [];
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'    => $user->email,
                'password' => 'password',
            ]);

            $response->assertSuccessful();
            $token = $response->json('access_token');
            $this->assertNotNull($token, "Token should not be null for iteration $i");
            $tokens[] = $token;
        }

        // Check that the oldest session is invalidated (only 5 sessions allowed)
        $response = $this->withToken($tokens[0])->getJson('/api/auth/user');
        $response->assertStatus(401);

        // But the latest sessions should still work
        $response = $this->withToken($tokens[5])->getJson('/api/auth/user');
        $response->assertSuccessful();
    }

    /**
     * Test API versioning security.
     */
    #[Test]
    public function test_api_versioning_security()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Test deprecated API version
        $response = $this->getJson('/api/v0/accounts');
        $response->assertStatus(404);

        // Test current versions
        $response = $this->getJson('/api/v1/accounts');
        $response->assertSuccessful();

        $response = $this->getJson('/api/v2/accounts');
        $response->assertSuccessful();

        // Test future version
        $response = $this->getJson('/api/v99/accounts');
        $response->assertStatus(404);
    }

    /**
     * Helper method to time request execution.
     */
    private function timeRequest(callable $request): float
    {
        $start = microtime(true);
        $request();

        return (microtime(true) - $start) * 1000; // Convert to milliseconds
    }
}
