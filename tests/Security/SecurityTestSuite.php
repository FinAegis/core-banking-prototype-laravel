<?php

namespace Tests\Security;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class SecurityTestSuite extends TestCase
{
    /**
     * Run all security tests and generate a security report
     *
     * @test
     */
    public function test_complete_security_suite()
    {
        $this->markTestIncomplete('This is a meta-test for running the complete security suite');
        
        // This test serves as documentation for running all security tests
        // Run with: ./vendor/bin/pest tests/Security --parallel
    }

    /**
     * @test
     */
    public function test_security_headers_are_configured()
    {
        $response = $this->get('/');
        
        $requiredHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => ['DENY', 'SAMEORIGIN'],
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => ['no-referrer', 'strict-origin-when-cross-origin', 'same-origin'],
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];

        foreach ($requiredHeaders as $header => $expectedValues) {
            $this->assertTrue(
                $response->headers->has($header),
                "Security header {$header} is missing"
            );

            if ($response->headers->has($header)) {
                $value = $response->headers->get($header);
                if (is_array($expectedValues)) {
                    $hasValidValue = false;
                    foreach ($expectedValues as $expected) {
                        if (str_contains($value, $expected) || $value === $expected) {
                            $hasValidValue = true;
                            break;
                        }
                    }
                    $this->assertTrue(
                        $hasValidValue,
                        "Header {$header} has unexpected value: {$value}"
                    );
                } else {
                    $this->assertEquals($expectedValues, $value);
                }
            }
        }
    }

    /**
     * @test
     */
    public function test_environment_configuration_is_secure()
    {
        // APP_DEBUG should be false in production
        if (app()->environment('production')) {
            $this->assertFalse(config('app.debug'), 'Debug mode should be disabled in production');
        }

        // APP_KEY should be set
        $this->assertNotEmpty(config('app.key'), 'Application key must be set');
        $this->assertStringStartsWith('base64:', config('app.key'));

        // Session configuration
        if (app()->environment('production')) {
            $this->assertTrue(config('session.secure'), 'Session cookies should be secure in production');
            $this->assertEquals('lax', config('session.same_site'), 'SameSite should be lax or strict');
        }

        // Database configuration shouldn't expose credentials
        $this->assertNotEquals('root', config('database.connections.mysql.username'));
        $this->assertNotEquals('password', config('database.connections.mysql.password'));
        $this->assertNotEquals('secret', config('database.connections.mysql.password'));
    }

    /**
     * @test
     */
    public function test_no_sensitive_files_are_exposed()
    {
        $sensitiveFiles = [
            '/.env',
            '/.env.example',
            '/.git/config',
            '/.gitignore',
            '/composer.json',
            '/composer.lock',
            '/package.json',
            '/webpack.config.js',
            '/phpunit.xml',
            '/.phpunit.result.cache',
            '/storage/logs/laravel.log',
            '/database/database.sqlite',
            '/.DS_Store',
            '/Thumbs.db',
        ];

        foreach ($sensitiveFiles as $file) {
            $response = $this->get($file);
            
            $this->assertContains(
                $response->status(),
                [403, 404],
                "Sensitive file {$file} should not be accessible"
            );
            
            // Content should not be exposed
            $content = $response->content();
            $this->assertStringNotContainsString('APP_KEY=', $content);
            $this->assertStringNotContainsString('DB_PASSWORD=', $content);
            $this->assertStringNotContainsString('"require":', $content); // composer.json
        }
    }

    /**
     * @test
     */
    public function test_error_pages_dont_leak_information()
    {
        // Force production environment for this test
        config(['app.debug' => false]);
        
        // Test 404 error
        $response = $this->get('/non-existent-page-12345');
        $this->assertEquals(404, $response->status());
        
        $content = $response->content();
        $this->assertStringNotContainsString('Laravel', $content);
        $this->assertStringNotContainsString('Symfony', $content);
        $this->assertStringNotContainsString('Stack trace', $content);
        $this->assertStringNotContainsString('/var/www', $content);
        $this->assertStringNotContainsString('/home/', $content);
        
        // Test 500 error (simulate)
        $response = $this->get('/api/trigger-500-error');
        if ($response->status() === 500) {
            $content = $response->content();
            $this->assertStringNotContainsString('Exception', $content);
            $this->assertStringNotContainsString('Error trace', $content);
            $this->assertStringNotContainsString('Database', $content);
        }
    }

    /**
     * @test
     */
    public function test_csrf_protection_is_enabled()
    {
        // For web routes
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);

        // Should require CSRF token for web routes
        if (!str_starts_with(request()->path(), 'api/')) {
            $this->assertEquals(419, $response->status());
        }
    }

    /**
     * @test
     */
    public function test_secure_password_hashing()
    {
        $hash = bcrypt('password');
        
        // Should use bcrypt with proper cost
        $this->assertStringStartsWith('$2y$', $hash);
        
        // Extract cost factor
        preg_match('/\$2y\$(\d+)\$/', $hash, $matches);
        $cost = isset($matches[1]) ? (int) $matches[1] : 0;
        
        // Cost should be at least 10 (preferably 12+)
        $this->assertGreaterThanOrEqual(10, $cost, 'Bcrypt cost factor should be at least 10');
    }

    /**
     * @test
     */
    public function test_no_default_users_exist()
    {
        $defaultCredentials = [
            ['email' => 'admin@admin.com', 'password' => 'admin'],
            ['email' => 'admin@example.com', 'password' => 'password'],
            ['email' => 'test@test.com', 'password' => 'test'],
            ['email' => 'demo@demo.com', 'password' => 'demo'],
            ['email' => 'user@user.com', 'password' => 'user'],
        ];

        foreach ($defaultCredentials as $creds) {
            $response = $this->postJson('/api/v2/auth/login', $creds);
            
            $this->assertNotEquals(
                200,
                $response->status(),
                "Default credentials {$creds['email']}:{$creds['password']} should not work"
            );
        }
    }

    /**
     * @test
     */
    public function test_session_security_configuration()
    {
        $sessionConfig = config('session');
        
        // Check secure session settings
        $this->assertEquals('lax', $sessionConfig['same_site'] ?? null);
        $this->assertTrue($sessionConfig['http_only'] ?? false);
        
        if (app()->environment('production')) {
            $this->assertTrue($sessionConfig['secure'] ?? false);
        }
        
        // Session lifetime should be reasonable
        $lifetime = $sessionConfig['lifetime'] ?? 120;
        $this->assertLessThanOrEqual(120, $lifetime, 'Session lifetime should not be too long');
        
        // Should use secure session driver
        $driver = $sessionConfig['driver'] ?? 'file';
        $this->assertNotEquals('array', $driver, 'Should not use array driver in production');
    }

    /**
     * Generate a security audit report
     */
    public static function generateSecurityReport(): array
    {
        return [
            'test_suite' => 'FinAegis Security Test Suite',
            'categories' => [
                'SQL Injection' => 'tests/Security/Penetration/SqlInjectionTest.php',
                'XSS Protection' => 'tests/Security/Penetration/XssTest.php',
                'CSRF Protection' => 'tests/Security/Penetration/CsrfTest.php',
                'Authentication' => 'tests/Security/Authentication/AuthenticationSecurityTest.php',
                'Authorization' => 'tests/Security/Authentication/AuthorizationSecurityTest.php',
                'API Security' => 'tests/Security/API/ApiSecurityTest.php',
                'Cryptography' => 'tests/Security/Cryptography/CryptographySecurityTest.php',
                'Input Validation' => 'tests/Security/Vulnerabilities/InputValidationTest.php',
            ],
            'commands' => [
                'Run all security tests' => './vendor/bin/pest tests/Security --parallel',
                'Run specific category' => './vendor/bin/pest tests/Security/Penetration',
                'Generate coverage report' => './vendor/bin/pest tests/Security --coverage',
            ],
            'recommendations' => [
                'Run security tests in CI/CD pipeline',
                'Schedule regular security audits',
                'Keep dependencies updated',
                'Monitor security advisories',
                'Implement security headers',
                'Use rate limiting',
                'Enable audit logging',
                'Implement proper encryption',
            ],
        ];
    }
}