<?php

namespace Tests\Security\Vulnerabilities;

use App\Models\User;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
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
     */
    public function test_numeric_input_boundary_validation()
    {
        $boundaryValues = [
            -1,
            0,
            1,
            PHP_INT_MAX,
            PHP_INT_MIN,
            9999999999999999999999, // Exceeds INT_MAX
            -9999999999999999999999,
            3.14159, // Float when expecting integer
            '1e10', // Scientific notation
            '0x1234', // Hexadecimal
            '0777', // Octal
            'NaN',
            'Infinity',
            '-Infinity',
        ];

        foreach ($boundaryValues as $amount) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/transfers', [
                    'from_account' => Account::factory()->create(['user_uuid' => $this->user->uuid])->uuid,
                    'to_account' => Account::factory()->create()->uuid,
                    'amount' => $amount,
                    'currency' => 'USD'
                ]);

            if (is_numeric($amount) && $amount > 0 && $amount < PHP_INT_MAX) {
                // Valid amounts might succeed
                $this->assertContains($response->status(), [201, 422]);
            } else {
                // Invalid amounts should be rejected
                $this->assertEquals(422, $response->status());
            }
        }
    }

    /**
     * @test
     */
    public function test_string_length_validation()
    {
        $lengths = [
            0 => '',
            1 => 'A',
            255 => str_repeat('A', 255),
            256 => str_repeat('A', 256),
            1000 => str_repeat('A', 1000),
            10000 => str_repeat('A', 10000),
            100000 => str_repeat('A', 100000),
        ];

        foreach ($lengths as $length => $value) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/accounts', [
                    'name' => $value,
                    'type' => 'savings'
                ]);

            if ($length === 0 || $length > 255) {
                // Should reject empty or too long names
                $this->assertEquals(422, $response->status());
                $this->assertArrayHasKey('name', $response->json('errors'));
            }
        }
    }

    /**
     * @test
     */
    public function test_email_validation_edge_cases()
    {
        $emails = [
            'valid@example.com' => true,
            'user+tag@example.com' => true,
            'user.name@example.co.uk' => true,
            'user@subdomain.example.com' => true,
            // Invalid cases
            'plaintext' => false,
            '@example.com' => false,
            'user@' => false,
            'user@@example.com' => false,
            'user@example' => false,
            'user @example.com' => false,
            'user@exam ple.com' => false,
            'user@.com' => false,
            'user@example..com' => false,
            'user@-example.com' => false,
            'user@example.com-' => false,
            '.user@example.com' => false,
            'user.@example.com' => false,
            'user@[192.168.1.1]' => true, // IP address
            'user@[2001:db8::1]' => true, // IPv6
            'user@localhost' => false, // No TLD
            // XSS attempts in email
            'user<script>alert(1)</script>@example.com' => false,
            'user@example.com<script>alert(1)</script>' => false,
            // SQL injection in email
            "user'OR'1'='1@example.com" => false,
            'user@example.com;DROP TABLE users;--' => false,
        ];

        foreach ($emails as $email => $shouldBeValid) {
            $response = $this->postJson('/api/v2/auth/register', [
                'name' => 'Test User',
                'email' => $email,
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!'
            ]);

            if ($shouldBeValid) {
                $this->assertContains($response->status(), [201, 422]); // Might fail for other reasons
            } else {
                $this->assertEquals(422, $response->status());
                $this->assertArrayHasKey('email', $response->json('errors'));
            }
        }
    }

    /**
     * @test
     */
    public function test_uuid_validation()
    {
        $uuids = [
            // Valid UUIDs
            '550e8400-e29b-41d4-a716-446655440000' => true,
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8' => true,
            '00000000-0000-0000-0000-000000000000' => true,
            // Invalid UUIDs
            'not-a-uuid' => false,
            '550e8400-e29b-41d4-a716' => false, // Too short
            '550e8400-e29b-41d4-a716-446655440000-extra' => false, // Too long
            '550e8400-xxxx-41d4-a716-446655440000' => false, // Invalid characters
            'g50e8400-e29b-41d4-a716-446655440000' => false, // Invalid hex
            '550e8400e29b41d4a716446655440000' => false, // No dashes
            '{550e8400-e29b-41d4-a716-446655440000}' => false, // With braces
            // SQL injection in UUID
            "'; DROP TABLE accounts; --" => false,
            "' OR '1'='1" => false,
            // Path traversal in UUID
            '../../../etc/passwd' => false,
            '..\\..\\..\\windows\\system32' => false,
        ];

        foreach ($uuids as $uuid => $shouldBeValid) {
            $response = $this->withToken($this->token)
                ->getJson("/api/v2/accounts/{$uuid}");

            if ($shouldBeValid) {
                // Valid UUID format (might still be 404 if doesn't exist)
                $this->assertContains($response->status(), [200, 403, 404]);
            } else {
                // Invalid UUID format should be rejected
                $this->assertContains($response->status(), [404, 422]);
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
                $this->assertContains($response->status(), [200, 422]);
            } else {
                $this->assertContains($response->status(), [422, 400]);
                
                // Should not process as valid date
                if ($response->status() === 200) {
                    $this->assertEmpty($response->json('data'));
                }
            }
        }
    }

    /**
     * @test
     */
    public function test_array_input_validation()
    {
        $arrays = [
            // Normal arrays
            ['USD', 'EUR', 'GBP'],
            // Empty array
            [],
            // Large array
            array_fill(0, 1000, 'USD'),
            // Nested arrays
            ['USD', ['EUR', 'GBP']],
            // Associative arrays
            ['currency' => 'USD', 'amount' => 100],
            // Mixed types
            ['USD', 123, true, null],
            // Injection attempts
            ["USD' OR '1'='1", 'EUR'],
            ['<script>alert(1)</script>', 'EUR'],
        ];

        foreach ($arrays as $array) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/bulk-operations', [
                    'operations' => $array
                ]);

            // Should handle arrays safely
            $this->assertContains($response->status(), [200, 201, 422, 404]);
            
            // Check response doesn't reflect dangerous content
            if ($response->status() < 400) {
                $content = $response->content();
                $this->assertStringNotContainsString('<script>', $content);
                $this->assertStringNotContainsString("OR '1'='1", $content);
            }
        }
    }

    /**
     * @test
     */
    public function test_file_upload_validation()
    {
        $files = [
            'document.pdf' => 'application/pdf',
            'image.jpg' => 'image/jpeg',
            'spreadsheet.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            // Dangerous extensions
            'script.php' => 'application/x-php',
            'shell.sh' => 'application/x-sh',
            'executable.exe' => 'application/x-executable',
            'webshell.jsp' => 'application/x-jsp',
            'script.js' => 'application/javascript',
            'page.html' => 'text/html',
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
                $this->assertEquals(422, $response->status());
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
                    'type' => 'savings'
                ]);

            if (!$shouldBeValid) {
                // Should sanitize or reject dangerous Unicode
                if ($response->status() === 201) {
                    $name = $response->json('data.name');
                    // Control characters should be stripped
                    $this->assertStringNotContainsString("\x00", $name);
                    $this->assertStringNotContainsString("\u{200B}", $name);
                    $this->assertStringNotContainsString("\u{202E}", $name);
                }
            }
        }
    }

    /**
     * @test
     */
    public function test_json_structure_validation()
    {
        $jsonPayloads = [
            // Deep nesting
            json_encode(array_fill_keys(range(0, 100), array_fill_keys(range(0, 100), 'value'))),
            // Circular reference simulation
            '{"a": {"b": {"c": {"d": "loop"}}}}',
            // Large arrays
            json_encode(['items' => array_fill(0, 10000, 'item')]),
            // Unicode edge cases
            json_encode(['name' => "\xC3\x28"]), // Invalid UTF-8
        ];

        foreach ($jsonPayloads as $payload) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/batch', [], ['Content-Type' => 'application/json'])
                ->withBody($payload, 'application/json');

            // Should handle gracefully
            $this->assertContains($response->status(), [400, 413, 422]);
            
            // Should not expose internal errors
            $content = $response->content();
            $this->assertStringNotContainsString('Maximum stack depth exceeded', $content);
            $this->assertStringNotContainsString('Allowed memory size exhausted', $content);
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
}