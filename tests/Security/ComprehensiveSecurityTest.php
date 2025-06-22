<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Account;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ComprehensiveSecurityTest extends TestCase
{
    /**
     * Test SQL injection prevention in various endpoints
     */
    public function test_sql_injection_prevention()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $injectionPayloads = [
            "'; DROP TABLE accounts; --",
            "1' OR '1'='1",
            "admin'--",
            "1; DELETE FROM users WHERE 1=1; --",
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
            
            // Verify tables still exist
            $this->assertDatabaseHas('accounts', ['id' => 1]);
            $this->assertDatabaseHas('users', ['id' => 1]);
        }
    }
    
    /**
     * Test XSS prevention in API responses
     */
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
                'name' => $payload,
                'user_uuid' => $user->uuid,
            ]);
            
            $response = $this->getJson("/api/accounts/{$account->uuid}");
            
            // Verify payload is escaped in response
            $response->assertSuccessful();
            $content = $response->getContent();
            
            $this->assertStringNotContainsString('<script>', $content);
            $this->assertStringNotContainsString('onerror=', $content);
            $this->assertStringNotContainsString('<iframe', $content);
        }
    }
    
    /**
     * Test authentication security
     */
    public function test_authentication_security()
    {
        $user = User::factory()->create([
            'password' => Hash::make('SecurePassword123!'),
        ]);
        
        // Test brute force protection
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'WrongPassword',
            ]);
            
            if ($i < 5) {
                $response->assertStatus(401);
            } else {
                $response->assertStatus(429); // Too many attempts
            }
        }
        
        // Test timing attack prevention
        $validTime = $this->timeRequest(function () use ($user) {
            return $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'SecurePassword123!',
            ]);
        });
        
        $invalidTime = $this->timeRequest(function () {
            return $this->postJson('/api/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'WrongPassword',
            ]);
        });
        
        // Times should be similar to prevent user enumeration
        $this->assertLessThan(100, abs($validTime - $invalidTime)); // Within 100ms
    }
    
    /**
     * Test CSRF protection
     */
    public function test_csrf_protection()
    {
        $user = User::factory()->create();
        
        // Test without CSRF token
        $response = $this->post('/web/accounts', [
            'name' => 'Test Account',
        ]);
        
        $response->assertStatus(419); // CSRF token mismatch
        
        // Test with valid CSRF token
        $this->actingAs($user);
        $response = $this->post('/web/accounts', [
            '_token' => csrf_token(),
            'name' => 'Test Account',
        ]);
        
        $response->assertSuccessful();
    }
    
    /**
     * Test API rate limiting
     */
    public function test_api_rate_limiting()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Clear rate limiter
        RateLimiter::clear('api:' . $user->id);
        
        // Test rate limit (60 requests per minute)
        for ($i = 0; $i < 61; $i++) {
            $response = $this->getJson('/api/accounts');
            
            if ($i < 60) {
                $response->assertSuccessful();
            } else {
                $response->assertStatus(429);
                $response->assertHeader('X-RateLimit-Limit', '60');
                $response->assertHeader('X-RateLimit-Remaining', '0');
            }
        }
    }
    
    /**
     * Test secure headers
     */
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
     * Test input validation
     */
    public function test_input_validation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Test oversized input
        $oversizedData = str_repeat('a', 1000001); // 1MB+ string
        
        $response = $this->postJson('/api/accounts', [
            'name' => $oversizedData,
        ]);
        
        $response->assertStatus(422);
        
        // Test invalid data types
        $response = $this->postJson('/api/transfers', [
            'from_account_uuid' => ['array', 'not', 'string'],
            'to_account_uuid' => true, // Boolean instead of string
            'amount' => 'not a number',
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from_account_uuid', 'to_account_uuid', 'amount']);
    }
    
    /**
     * Test secure password requirements
     */
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
                'name' => 'Test User',
                'email' => Str::random() . '@example.com',
                'password' => $password,
                'password_confirmation' => $password,
            ]);
            
            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['password']);
        }
        
        // Test strong password
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => Str::random() . '@example.com',
            'password' => 'SecureP@ssw0rd123!',
            'password_confirmation' => 'SecureP@ssw0rd123!',
        ]);
        
        $response->assertSuccessful();
    }
    
    /**
     * Test file upload security
     */
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
        $response = $this->postJson('/api/kyc/documents', [
            'document' => \Illuminate\Http\UploadedFile::fake()->image('passport.jpg', 800, 600),
        ]);
        
        $response->assertSuccessful();
    }
    
    /**
     * Test session security
     */
    public function test_session_security()
    {
        $user = User::factory()->create();
        
        // Login and get session
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        
        $token = $response->json('token');
        
        // Test session timeout
        $this->travel(31)->minutes();
        
        $response = $this->withToken($token)->getJson('/api/user');
        $response->assertStatus(401); // Session expired
        
        // Test concurrent session limit
        $tokens = [];
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);
            
            if ($i < 5) {
                $response->assertSuccessful();
                $tokens[] = $response->json('token');
            } else {
                // Oldest session should be invalidated
                $response = $this->withToken($tokens[0])->getJson('/api/user');
                $response->assertStatus(401);
            }
        }
    }
    
    /**
     * Test API versioning security
     */
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
     * Helper method to time request execution
     */
    private function timeRequest(callable $request): float
    {
        $start = microtime(true);
        $request();
        return (microtime(true) - $start) * 1000; // Convert to milliseconds
    }
}