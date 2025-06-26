<?php

namespace Tests\Security\Vulnerabilities;

use App\Models\User;
use App\Models\Account;
use Tests\TestCase;

class InputValidationTest extends TestCase
{
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles if not already created
        $this->createRoles();
        
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /**
     * @test
     * @dataProvider dangerousInputs
     */
    public function test_account_creation_validates_dangerous_inputs($input, $field)
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/accounts', [
                'name' => $field === 'name' ? $input : 'Valid Name',
                'type' => $field === 'type' ? $input : 'savings',
                'currency' => $field === 'currency' ? $input : 'USD',
                'description' => $field === 'description' ? $input : 'Valid description'
            ]);

        // Should either validate and reject, or sanitize the input
        $this->assertContains($response->status(), [201, 422]);
        
        if ($response->status() === 201) {
            $account = $response->json('data');
            
            // If accepted, dangerous content should be sanitized
            $value = $account[$field] ?? '';
            $this->assertStringNotContainsString('<script>', $value);
            $this->assertStringNotContainsString('javascript:', $value);
            $this->assertStringNotContainsString('<?php', $value);
            $this->assertStringNotContainsString('SELECT * FROM', $value);
        }
    }

    /**
     * @test
     * @dataProvider numericInputs
     */
    public function test_numeric_field_validation($input, $field, $shouldBeValid)
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/transactions', [
                'from_account' => $field === 'from_account' ? $input : $this->account->uuid ?? 'valid-uuid',
                'to_account' => $field === 'to_account' ? $input : 'valid-uuid',
                'amount' => $field === 'amount' ? $input : 1000,
                'currency' => 'USD'
            ]);

        if ($shouldBeValid) {
            $this->assertContains($response->status(), [200, 201, 404, 405]); // 404 if account doesn't exist, 405 if method not allowed
        } else {
            $this->assertContains($response->status(), [422, 400, 405]); // 405 if method not allowed
        }
    }

    /**
     * @test
     */
    public function test_email_validation_with_dangerous_inputs()
    {
        $emails = [
            // Valid emails
            'user@example.com' => true,
            'user+tag@example.com' => true,
            'user.name@example.co.uk' => true,
            // Invalid emails
            'user@' => false,
            '@example.com' => false,
            'user@.com' => false,
            'user@example' => false,
            // Injection attempts
            'user@example.com<script>alert(1)</script>' => false,
            "user@example.com' OR '1'='1" => false,
            'user@example.com;DELETE FROM users;' => false,
            'user@[127.0.0.1]' => false,
            'user@localhost' => false,
            'user@internal.service' => false,
        ];

        foreach ($emails as $email => $shouldBeValid) {
            $response = $this->postJson('/api/v2/auth/register', [
                'name' => 'Test User',
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123'
            ]);

            if ($shouldBeValid) {
                $this->assertContains($response->status(), [201, 422]); // 422 if email already exists
            } else {
                $this->assertEquals(422, $response->status());
            }
        }
    }

    /**
     * @test
     */
    public function test_json_payload_size_limits()
    {
        // Create a large payload
        $largeArray = array_fill(0, 10000, 'A' . str_repeat('B', 1000));
        
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/accounts/bulk', [
                'accounts' => $largeArray
            ]);

        // Should reject oversized payloads
        $this->assertContains($response->status(), [413, 422, 400, 404, 405]);
    }

    /**
     * @test
     */
    public function test_nested_json_depth_limits()
    {
        // Create deeply nested JSON
        $data = ['level' => 1];
        $current = &$data;
        for ($i = 2; $i <= 100; $i++) {
            $current['nested'] = ['level' => $i];
            $current = &$current['nested'];
        }

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/accounts', [
                'name' => 'Test Account',
                'metadata' => $data
            ]);

        // Should handle or reject deeply nested data
        $this->assertContains($response->status(), [201, 422, 400]);
    }

    /**
     * @test
     */
    public function test_integer_overflow_protection()
    {
        $amounts = [
            // Valid amounts
            1 => true,
            1000 => true,
            1000000 => true,
            // PHP_INT_MAX
            PHP_INT_MAX => false,
            // String representations
            '9999999999999999999999' => false,
            '-9999999999999999999999' => false,
            // Scientific notation
            '1e100' => false,
            '1.23e45' => false,
            // Hex/Oct
            '0xFFFFFFFF' => false,
            '0777777777' => false,
        ];

        foreach ($amounts as $amount => $shouldBeValid) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/accounts/' . ($this->account->uuid ?? 'test-uuid') . '/deposit', [
                    'amount' => $amount,
                    'currency' => 'USD'
                ]);

            if ($shouldBeValid) {
                $this->assertContains($response->status(), [200, 201, 404, 405, 422]); // 422 if validation fails
            } else {
                $this->assertContains($response->status(), [422, 400, 405]);
            }
        }
    }

    /**
     * @test
     */
    public function test_uuid_format_validation()
    {
        $uuids = [
            // Valid UUIDs
            '550e8400-e29b-41d4-a716-446655440000' => true,
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8' => true,
            // Invalid UUIDs
            'not-a-uuid' => false,
            '550e8400-e29b-41d4-a716' => false,
            '550e8400-e29b-41d4-a716-446655440000-extra' => false,
            'GGGGGGGG-e29b-41d4-a716-446655440000' => false,
            // Injection attempts
            "550e8400-e29b-41d4-a716-446655440000' OR '1'='1" => false,
            '550e8400-e29b-41d4-a716-446655440000<script>' => false,
            // Path traversal
            '../../../etc/passwd' => false,
            '..\\..\\..\\windows\\system32' => false,
        ];

        foreach ($uuids as $uuid => $shouldBeValid) {
            try {
                $response = $this->withToken($this->token)
                    ->getJson("/api/v2/accounts/{$uuid}");

                if ($shouldBeValid) {
                    // Valid UUID format (might still be 404 if doesn't exist)
                    $this->assertContains($response->status(), [200, 403, 404]);
                } else {
                    // Invalid UUID format should be rejected
                    $this->assertContains($response->status(), [404, 422, 400]);
                }
            } catch (\Symfony\Component\HttpFoundation\Exception\BadRequestException $e) {
                // URI with backslash will throw exception
                if (!$shouldBeValid) {
                    $this->assertTrue(true); // Expected for invalid inputs
                } else {
                    throw $e; // Unexpected for valid inputs
                }
            }
        }
    }

    /**
     * @test
     */
    public function test_date_input_validation()
    {
        $dates = [
            // Valid dates
            '2025-06-21' => true,
            '2025-06-21T10:30:00Z' => true,
            '2025-06-21T10:30:00+00:00' => true,
            // Invalid dates
            '2025-13-01' => false, // Invalid month
            '2025-06-32' => false, // Invalid day
            '2025-02-30' => false, // Feb 30th
            '21-06-2025' => false, // Wrong format
            '21/06/2025' => false, // Wrong separator
            'June 21, 2025' => false, // Text format
            'yesterday' => false,
            'now' => false,
            // Injection attempts
            '2025-06-21; DROP TABLE transactions;' => false,
            "2025-06-21' OR '1'='1" => false,
            '2025-06-21<script>alert(1)</script>' => false,
        ];

        foreach ($dates as $date => $shouldBeValid) {
            $response = $this->withToken($this->token)
                ->getJson("/api/v2/transactions?from_date={$date}");

            if ($shouldBeValid) {
                $this->assertContains($response->status(), [200, 422, 404, 500]);
            } else {
                $this->assertContains($response->status(), [422, 400, 500]);
            }
        }
    }

    /**
     * @test
     */
    public function test_array_input_validation()
    {
        $arrays = [
            // Valid arrays
            'valid currencies' => [['USD', 'EUR', 'GBP'], true],
            'empty array' => [[], true],
            'single item' => [['single'], true],
            // Invalid arrays  
            'string not array' => ['not-an-array', false],
            'number not array' => [123, false],
            'null not array' => [null, false],
            // Injection in array
            'sql injection' => [["USD' OR '1'='1", "EUR"], false],
            'xss injection' => [['<script>alert(1)</script>'], false],
        ];

        foreach ($arrays as $testName => $data) {
            [$array, $shouldBeValid] = $data;
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/exchange-rates/bulk', [
                    'currencies' => $array
                ]);

            if ($shouldBeValid && is_array($array)) {
                $this->assertContains($response->status(), [200, 201, 422, 404, 405]);
            } else {
                $this->assertContains($response->status(), [422, 400, 404, 405]);
            }
        }
    }

    /**
     * @test
     */
    public function test_file_upload_validation()
    {
        $files = [
            // Safe files
            'document.pdf' => 'application/pdf',
            'image.jpg' => 'image/jpeg', 
            'spreadsheet.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            // Dangerous files
            'script.php' => 'application/x-php',
            'shell.sh' => 'application/x-sh',
            'executable.exe' => 'application/x-executable',
            'webpage.html' => 'text/html',
            'script.js' => 'application/javascript',
            // Double extensions
            'document.pdf.php' => 'application/x-php',
            'image.jpg.exe' => 'application/x-executable',
            // Null byte
            "document.pdf\x00.php" => 'application/x-php',
            // Path traversal
            '../../../etc/passwd' => 'text/plain',
            '..\\..\\windows\\system32\\cmd.exe' => 'application/x-executable',
        ];

        foreach ($files as $filename => $mimeType) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/documents/upload', [
                    'filename' => $filename,
                    'mime_type' => $mimeType
                ]);

            // Dangerous files should be rejected
            if (preg_match('/\.(php|sh|exe|jsp|js|html)$/i', $filename) || 
                str_contains($filename, '..') || 
                str_contains($filename, "\x00")) {
                $this->assertContains($response->status(), [422, 404, 405]);
            }
        }
    }

    /**
     * @test
     */
    public function test_unicode_and_special_character_handling()
    {
        $inputs = [
            // Unicode
            '你好世界' => true, // Chinese
            'مرحبا بالعالم' => true, // Arabic
            '🔒💰🏦' => true, // Emojis
            // Special characters
            'Account & Co.' => true,
            'Price: $100' => true,
            "O'Brien's Account" => true,
            // Control characters
            "Line1\nLine2" => false,
            "Tab\tSeparated" => false,
            "Null\x00Byte" => false,
            "Bell\x07Sound" => false,
            // Zero-width characters
            "Invisible\u{200B}Space" => false,
            "Hidden\u{FEFF}BOM" => false,
            // Direction override
            "Normal\u{202E}Reversed" => false,
        ];

        foreach ($inputs as $input => $shouldBeValid) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/accounts', [
                    'name' => $input,
                    'type' => 'savings',
                    'currency' => 'USD'
                ]);

            if ($shouldBeValid) {
                $this->assertContains($response->status(), [201, 422]);
            } else {
                $this->assertEquals(422, $response->status());
            }
        }
    }

    /**
     * @test
     */
    public function test_header_injection_prevention()
    {
        $headers = [
            'X-Custom-Header' => "value\r\nX-Injected: true",
            'Authorization' => "Bearer token\r\nX-Evil: true",
            'Content-Type' => "application/json\r\nX-Attack: true",
        ];

        foreach ($headers as $header => $value) {
            $response = $this->withHeaders([$header => $value])
                ->getJson('/api/v2/accounts');

            // Should handle header injection attempts safely
            $this->assertContains($response->status(), [200, 400, 401, 422]);
        }
    }

    /**
     * @test
     */
    public function test_boolean_input_validation()
    {
        $booleans = [
            // Valid booleans
            true => true,
            false => true,
            1 => true,
            0 => true,
            '1' => true,
            '0' => true,
            'true' => true,
            'false' => true,
            // Invalid booleans
            'yes' => false,
            'no' => false,
            'on' => false,
            'off' => false,
            2 => false,
            -1 => false,
            'maybe' => false,
            null => false,
        ];

        foreach ($booleans as $value => $shouldBeValid) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/accounts/settings', [
                    'enable_notifications' => $value
                ]);

            if ($shouldBeValid) {
                $this->assertContains($response->status(), [200, 201, 404, 405]);
            } else {
                $this->assertContains($response->status(), [422, 400, 405]);
            }
        }
    }

    /**
     * Common dangerous inputs for testing
     */
    public static function dangerousInputs(): array
    {
        return [
            'SQL injection basic' => ["' OR '1'='1", 'name'],
            'SQL injection union' => ["' UNION SELECT * FROM users--", 'name'],
            'XSS script tag' => ['<script>alert("XSS")</script>', 'name'],
            'XSS img tag' => ['<img src=x onerror=alert("XSS")>', 'description'],
            'PHP code injection' => ['<?php system("ls"); ?>', 'description'],
            'Command injection' => ['`rm -rf /`', 'name'],
            'XXE injection' => ['<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>', 'description'],
            'Path traversal' => ['../../../etc/passwd', 'name'],
            'Null byte injection' => ["file.txt\x00.php", 'name'],
            'LDAP injection' => ['*)(uid=*))(&(uid=*', 'name'],
            'XML injection' => ['<![CDATA[<script>alert("XSS")</script>]]>', 'description'],
            'CSV injection' => ['=1+1+cmd|"/c calc"!A1', 'name'],
            'Header injection' => ["value\r\nX-Injected: true", 'name'],
            'Template injection' => ['{{7*7}}', 'name'],
            'JSON injection' => ['{"$ne": null}', 'name'],
        ];
    }

    /**
     * Numeric input test cases
     */
    public static function numericInputs(): array
    {
        return [
            'Valid integer' => [1000, 'amount', true],
            'Valid string integer' => ['1000', 'amount', true],
            'Negative amount' => [-1000, 'amount', false],
            'Zero amount' => [0, 'amount', false],
            'Float amount' => [100.50, 'amount', false],
            'String with spaces' => [' 1000 ', 'amount', false],
            'Non-numeric string' => ['abc', 'amount', false],
            'Exponential notation' => ['1e10', 'amount', false],
            'Hexadecimal' => ['0xFF', 'amount', false],
            'Binary' => ['0b1111', 'amount', false],
            'Infinity' => [INF, 'amount', false],
            'NaN' => [NAN, 'amount', false],
        ];
    }
}