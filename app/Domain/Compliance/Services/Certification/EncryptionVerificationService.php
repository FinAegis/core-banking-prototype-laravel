<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services\Certification;

use Illuminate\Support\Facades\Config;
use Pdo\Mysql as PdoMysql;

class EncryptionVerificationService
{
    /**
     * Run full encryption verification suite.
     *
     * @return array<string, mixed>
     */
    public function runVerification(): array
    {
        $results = [
            'at_rest'              => $this->verifyAtRest(),
            'in_transit'           => $this->verifyInTransit(),
            'key_strength'         => $this->verifyKeyStrength(),
            'algorithm_compliance' => $this->verifyAlgorithms(),
        ];

        $totalChecks = 0;
        $passedChecks = 0;

        foreach ($results as $category) {
            foreach ($category['checks'] ?? [] as $check) {
                $totalChecks++;
                if ($check['passed'] ?? false) {
                    $passedChecks++;
                }
            }
        }

        $results['summary'] = [
            'total_checks' => $totalChecks,
            'passed'       => $passedChecks,
            'failed'       => $totalChecks - $passedChecks,
            'score'        => $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100, 2) : 0,
            'verified_at'  => now()->toIso8601String(),
        ];

        return $results;
    }

    /**
     * Verify encryption at rest.
     *
     * @return array<string, mixed>
     */
    public function verifyAtRest(): array
    {
        $checks = [];

        // Check Laravel encryption cipher
        $cipher = Config::get('app.cipher', 'AES-256-CBC');
        $checks[] = [
            'name'     => 'Application encryption cipher',
            'passed'   => in_array($cipher, ['AES-256-CBC', 'AES-256-GCM'], true),
            'current'  => $cipher,
            'expected' => 'AES-256-CBC or AES-256-GCM',
        ];

        // Check APP_KEY is set
        $appKey = Config::get('app.key');
        $checks[] = [
            'name'     => 'Application key configured',
            'passed'   => ! empty($appKey) && strlen((string) $appKey) >= 32,
            'current'  => ! empty($appKey) ? 'Set (hidden)' : 'Not set',
            'expected' => 'Base64-encoded 256-bit key',
        ];

        // Check database encryption (connection uses SSL)
        $dbConnection = Config::get('database.default');
        $dbConfig = Config::get("database.connections.{$dbConnection}", []);
        $sslEnabled = ! empty($dbConfig['options'][PdoMysql::ATTR_SSL_CA] ?? null)
            || ! empty($dbConfig['sslmode'] ?? null);
        $checks[] = [
            'name'     => 'Database connection encryption',
            'passed'   => $sslEnabled || Config::get('app.env') === 'local',
            'current'  => $sslEnabled ? 'SSL/TLS enabled' : 'Not configured',
            'expected' => 'SSL/TLS encrypted connection',
        ];

        // Check session encryption
        $sessionDriver = Config::get('session.driver');
        $sessionEncrypt = Config::get('session.encrypt', false);
        $checks[] = [
            'name'     => 'Session data encryption',
            'passed'   => $sessionEncrypt || $sessionDriver === 'cookie',
            'current'  => $sessionEncrypt ? 'Encrypted' : 'Not encrypted',
            'expected' => 'Session data should be encrypted',
        ];

        return [
            'category' => 'Encryption at Rest',
            'checks'   => $checks,
            'passed'   => collect($checks)->every('passed'),
        ];
    }

    /**
     * Verify encryption in transit.
     *
     * @return array<string, mixed>
     */
    public function verifyInTransit(): array
    {
        $checks = [];

        // Check HTTPS enforcement
        $appUrl = (string) Config::get('app.url', '');
        $forceHttps = Config::get('app.force_https', false);
        $checks[] = [
            'name'     => 'HTTPS enforcement',
            'passed'   => str_starts_with($appUrl, 'https://') || $forceHttps || Config::get('app.env') === 'local',
            'current'  => str_starts_with($appUrl, 'https://') ? 'HTTPS' : 'HTTP',
            'expected' => 'HTTPS enforced',
        ];

        // Check HSTS header configuration
        $hstsEnabled = Config::get('secure-headers.hsts.max-age', 0) > 0;
        $checks[] = [
            'name'     => 'HSTS header configuration',
            'passed'   => $hstsEnabled || Config::get('app.env') === 'local',
            'current'  => $hstsEnabled ? 'Enabled' : 'Not configured',
            'expected' => 'HSTS with max-age > 0',
        ];

        // Check secure cookie settings
        $secureCookies = Config::get('session.secure', false);
        $checks[] = [
            'name'     => 'Secure cookie flag',
            'passed'   => $secureCookies || Config::get('app.env') === 'local',
            'current'  => $secureCookies ? 'Enabled' : 'Disabled',
            'expected' => 'Secure flag enabled for cookies',
        ];

        return [
            'category' => 'Encryption in Transit',
            'checks'   => $checks,
            'passed'   => collect($checks)->every('passed'),
        ];
    }

    /**
     * Verify cryptographic key strength.
     *
     * @return array<string, mixed>
     */
    public function verifyKeyStrength(): array
    {
        $checks = [];

        // Check APP_KEY length (should be 256-bit)
        $appKey = Config::get('app.key');
        $keyLength = $appKey ? strlen(base64_decode(str_replace('base64:', '', (string) $appKey))) * 8 : 0;
        $checks[] = [
            'name'     => 'Application key strength',
            'passed'   => $keyLength >= 256,
            'current'  => "{$keyLength}-bit",
            'expected' => '256-bit minimum',
        ];

        // Check bcrypt cost factor
        $bcryptRounds = (int) Config::get('hashing.bcrypt.rounds', 12);
        $checks[] = [
            'name'     => 'Password hashing strength',
            'passed'   => $bcryptRounds >= 10,
            'current'  => "Bcrypt rounds: {$bcryptRounds}",
            'expected' => 'Minimum 10 rounds',
        ];

        // Check JWT/Sanctum token configuration
        $sanctumExpiration = Config::get('sanctum.expiration');
        $checks[] = [
            'name'     => 'API token expiration',
            'passed'   => $sanctumExpiration !== null && $sanctumExpiration <= 1440,
            'current'  => $sanctumExpiration ? "{$sanctumExpiration} minutes" : 'No expiration',
            'expected' => 'Token expiration <= 24 hours',
        ];

        return [
            'category' => 'Key Strength',
            'checks'   => $checks,
            'passed'   => collect($checks)->every('passed'),
        ];
    }

    /**
     * Verify cryptographic algorithms are PCI DSS compliant.
     *
     * @return array<string, mixed>
     */
    public function verifyAlgorithms(): array
    {
        $checks = [];

        // Approved algorithms for PCI DSS
        $approvedCiphers = ['AES-128-CBC', 'AES-256-CBC', 'AES-128-GCM', 'AES-256-GCM'];
        $currentCipher = Config::get('app.cipher', 'AES-256-CBC');
        $checks[] = [
            'name'     => 'Encryption algorithm compliance',
            'passed'   => in_array($currentCipher, $approvedCiphers, true),
            'current'  => $currentCipher,
            'expected' => 'AES-128/256 in CBC or GCM mode',
        ];

        // Check configured key rotation algorithm
        $rotationAlgorithm = Config::get('compliance-certification.pci_dss.key_rotation.algorithm', 'AES-256-GCM');
        $checks[] = [
            'name'     => 'Key rotation algorithm',
            'passed'   => in_array($rotationAlgorithm, $approvedCiphers, true),
            'current'  => $rotationAlgorithm,
            'expected' => 'PCI DSS approved algorithm',
        ];

        // Check available OpenSSL ciphers
        $availableCiphers = openssl_get_cipher_methods();
        $hasAes256 = in_array('aes-256-gcm', $availableCiphers, true);
        $checks[] = [
            'name'     => 'OpenSSL AES-256-GCM availability',
            'passed'   => $hasAes256,
            'current'  => $hasAes256 ? 'Available' : 'Not available',
            'expected' => 'AES-256-GCM supported by OpenSSL',
        ];

        return [
            'category' => 'Algorithm Compliance',
            'checks'   => $checks,
            'passed'   => collect($checks)->every('passed'),
        ];
    }

    /**
     * Get demo verification results.
     *
     * @return array<string, mixed>
     */
    public function getDemoResults(): array
    {
        return [
            'at_rest' => [
                'category' => 'Encryption at Rest',
                'checks'   => [
                    ['name' => 'Application encryption cipher', 'passed' => true, 'current' => 'AES-256-GCM', 'expected' => 'AES-256-CBC or AES-256-GCM'],
                    ['name' => 'Application key configured', 'passed' => true, 'current' => 'Set (hidden)', 'expected' => 'Base64-encoded 256-bit key'],
                    ['name' => 'Database connection encryption', 'passed' => true, 'current' => 'SSL/TLS enabled', 'expected' => 'SSL/TLS encrypted connection'],
                    ['name' => 'Session data encryption', 'passed' => true, 'current' => 'Encrypted', 'expected' => 'Session data should be encrypted'],
                ],
                'passed' => true,
            ],
            'in_transit' => [
                'category' => 'Encryption in Transit',
                'checks'   => [
                    ['name' => 'HTTPS enforcement', 'passed' => true, 'current' => 'HTTPS', 'expected' => 'HTTPS enforced'],
                    ['name' => 'HSTS header configuration', 'passed' => true, 'current' => 'Enabled', 'expected' => 'HSTS with max-age > 0'],
                    ['name' => 'Secure cookie flag', 'passed' => true, 'current' => 'Enabled', 'expected' => 'Secure flag enabled for cookies'],
                ],
                'passed' => true,
            ],
            'key_strength' => [
                'category' => 'Key Strength',
                'checks'   => [
                    ['name' => 'Application key strength', 'passed' => true, 'current' => '256-bit', 'expected' => '256-bit minimum'],
                    ['name' => 'Password hashing strength', 'passed' => true, 'current' => 'Bcrypt rounds: 12', 'expected' => 'Minimum 10 rounds'],
                    ['name' => 'API token expiration', 'passed' => true, 'current' => '60 minutes', 'expected' => 'Token expiration <= 24 hours'],
                ],
                'passed' => true,
            ],
            'algorithm_compliance' => [
                'category' => 'Algorithm Compliance',
                'checks'   => [
                    ['name' => 'Encryption algorithm compliance', 'passed' => true, 'current' => 'AES-256-GCM', 'expected' => 'AES-128/256 in CBC or GCM mode'],
                    ['name' => 'Key rotation algorithm', 'passed' => true, 'current' => 'AES-256-GCM', 'expected' => 'PCI DSS approved algorithm'],
                    ['name' => 'OpenSSL AES-256-GCM availability', 'passed' => true, 'current' => 'Available', 'expected' => 'AES-256-GCM supported by OpenSSL'],
                ],
                'passed' => true,
            ],
            'summary' => [
                'total_checks' => 13,
                'passed'       => 13,
                'failed'       => 0,
                'score'        => 100.0,
                'verified_at'  => now()->toIso8601String(),
            ],
        ];
    }
}
