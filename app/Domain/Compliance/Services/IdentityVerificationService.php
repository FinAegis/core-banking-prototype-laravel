<?php

namespace App\Domain\Compliance\Services;

use InvalidArgumentException;

class IdentityVerificationService
{
    private array $providers = [
        'jumio' => [
            'endpoint' => 'https://api.jumio.com/v1/',
            'api_key'  => null,
        ],
        'onfido' => [
            'endpoint' => 'https://api.onfido.com/v3/',
            'api_key'  => null,
        ],
    ];

    public function __construct()
    {
        $this->providers['jumio']['api_key'] = config('services.jumio.api_key');
        $this->providers['onfido']['api_key'] = config('services.onfido.api_key');
    }

    /**
     * Verify identity against external databases.
     */
    public function verifyIdentity(array $data): array
    {
        // In production, this would make actual API calls to identity verification services
        // For demonstration, simulate the verification

        $results = [
            'match_found'      => false,
            'match_confidence' => 0,
            'sources_checked'  => [],
            'discrepancies'    => [],
        ];

        // Simulate identity database check
        $identityCheck = $this->checkIdentityDatabase($data);
        $results['sources_checked'][] = 'National Identity Database';

        if ($identityCheck['found']) {
            $results['match_found'] = true;
            $results['match_confidence'] = $identityCheck['confidence'];

            // Check for discrepancies
            $discrepancies = $this->findDiscrepancies($data, $identityCheck['data']);
            if (! empty($discrepancies)) {
                $results['discrepancies'] = $discrepancies;
                $results['match_confidence'] -= count($discrepancies) * 10;
            }
        }

        // Check against credit bureaus
        $creditCheck = $this->checkCreditBureaus($data);
        $results['sources_checked'][] = 'Credit Bureaus';

        if ($creditCheck['found'] && ! $results['match_found']) {
            $results['match_found'] = true;
            $results['match_confidence'] = $creditCheck['confidence'] * 0.8; // Lower weight for credit bureau
        }

        return $results;
    }

    /**
     * Create verification session with provider.
     */
    public function createVerificationSession(string $provider, array $userData): array
    {
        switch ($provider) {
            case 'jumio':
                return $this->createJumioSession($userData);
            case 'onfido':
                return $this->createOnfidoSession($userData);
            default:
                throw new InvalidArgumentException("Unknown provider: {$provider}");
        }
    }

    /**
     * Get verification result from provider.
     */
    public function getVerificationResult(string $provider, string $sessionId): array
    {
        switch ($provider) {
            case 'jumio':
                return $this->getJumioResult($sessionId);
            case 'onfido':
                return $this->getOnfidoResult($sessionId);
            default:
                throw new InvalidArgumentException("Unknown provider: {$provider}");
        }
    }

    /**
     * Check identity database (simulated).
     */
    protected function checkIdentityDatabase(array $data): array
    {
        // Simulate database lookup
        $firstName = strtolower($data['first_name'] ?? '');
        $lastName = strtolower($data['last_name'] ?? '');
        $dob = $data['date_of_birth'] ?? '';

        // Simulate positive match for testing
        if ($firstName === 'john' && $lastName === 'doe') {
            return [
                'found'      => true,
                'confidence' => 95,
                'data'       => [
                    'first_name'    => 'John',
                    'last_name'     => 'Doe',
                    'date_of_birth' => $dob,
                    'nationality'   => 'US',
                    'id_number'     => 'ID123456789',
                ],
            ];
        }

        return ['found' => false];
    }

    /**
     * Check credit bureaus (simulated).
     */
    protected function checkCreditBureaus(array $data): array
    {
        // Simulate credit bureau check
        return [
            'found'      => rand(0, 10) > 3, // 70% chance of finding record
            'confidence' => rand(70, 90),
        ];
    }

    /**
     * Find discrepancies between provided and verified data.
     */
    protected function findDiscrepancies(array $provided, array $verified): array
    {
        $discrepancies = [];
        $fields = ['first_name', 'last_name', 'date_of_birth', 'nationality'];

        foreach ($fields as $field) {
            if (isset($provided[$field]) && isset($verified[$field])) {
                if (strtolower($provided[$field]) !== strtolower($verified[$field])) {
                    $discrepancies[] = [
                        'field'    => $field,
                        'provided' => $provided[$field],
                        'verified' => $verified[$field],
                    ];
                }
            }
        }

        return $discrepancies;
    }

    /**
     * Create Jumio verification session.
     */
    protected function createJumioSession(array $userData): array
    {
        // In production, this would make actual API call
        // Simulated response
        return [
            'session_id'   => 'jumio_' . uniqid(),
            'redirect_url' => 'https://example.jumio.com/verify/' . uniqid(),
            'expires_at'   => now()->addHours(24)->toIso8601String(),
        ];
    }

    /**
     * Create Onfido verification session.
     */
    protected function createOnfidoSession(array $userData): array
    {
        // In production, this would make actual API call
        // Simulated response
        return [
            'session_id'   => 'onfido_' . uniqid(),
            'sdk_token'    => 'sdk_' . uniqid(),
            'applicant_id' => 'app_' . uniqid(),
            'expires_at'   => now()->addHours(24)->toIso8601String(),
        ];
    }

    /**
     * Get Jumio verification result.
     */
    protected function getJumioResult(string $sessionId): array
    {
        // In production, this would make actual API call
        // Simulated response
        return [
            'status'     => 'completed',
            'result'     => 'passed',
            'confidence' => 92.5,
            'checks'     => [
                'document_validity' => true,
                'face_match'        => true,
                'data_extraction'   => true,
                'fraud_check'       => true,
            ],
            'extracted_data' => [
                'first_name'      => 'John',
                'last_name'       => 'Doe',
                'date_of_birth'   => '1990-01-01',
                'document_number' => 'P123456789',
                'document_type'   => 'passport',
                'issuing_country' => 'US',
                'expiry_date'     => '2025-12-31',
            ],
        ];
    }

    /**
     * Get Onfido verification result.
     */
    protected function getOnfidoResult(string $sessionId): array
    {
        // In production, this would make actual API call
        // Simulated response
        return [
            'status'     => 'complete',
            'result'     => 'clear',
            'sub_result' => 'clear',
            'reports'    => [
                [
                    'name'      => 'document',
                    'result'    => 'clear',
                    'breakdown' => [
                        'data_extraction'     => 'clear',
                        'data_validation'     => 'clear',
                        'image_integrity'     => 'clear',
                        'visual_authenticity' => 'clear',
                    ],
                ],
                [
                    'name'      => 'facial_similarity_photo',
                    'result'    => 'clear',
                    'breakdown' => [
                        'face_match'      => 'clear',
                        'image_integrity' => 'clear',
                    ],
                ],
            ],
        ];
    }
}
