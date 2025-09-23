<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Models\AgentTransaction;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionVerificationService
{
    // Verification levels
    private const VERIFICATION_LEVELS = [
        'basic'    => ['signature', 'agent'],
        'standard' => ['signature', 'agent', 'limits', 'velocity'],
        'enhanced' => ['signature', 'agent', 'limits', 'velocity', 'fraud', 'compliance'],
        'maximum'  => ['signature', 'agent', 'limits', 'velocity', 'fraud', 'compliance', 'encryption', 'multi_factor'],
    ];

    // Risk thresholds
    private const RISK_THRESHOLDS = [
        'low'      => 30,
        'medium'   => 60,
        'high'     => 80,
        'critical' => 95,
    ];

    public function __construct(
        private readonly DigitalSignatureService $signatureService,
        private readonly EncryptionService $encryptionService,
        private readonly FraudDetectionService $fraudService
    ) {
    }

    /**
     * Perform comprehensive transaction verification.
     */
    public function verifyTransaction(
        string $transactionId,
        string $agentId,
        array $transactionData,
        array $securityMetadata,
        string $verificationLevel = 'standard'
    ): array {
        try {
            $verificationResults = [
                'transaction_id'     => $transactionId,
                'agent_id'           => $agentId,
                'verification_level' => $verificationLevel,
                'timestamp'          => now()->toIso8601String(),
                'checks'             => [],
            ];

            // Get verification checks for level
            $requiredChecks = self::VERIFICATION_LEVELS[$verificationLevel] ?? self::VERIFICATION_LEVELS['standard'];

            // Perform each verification check
            foreach ($requiredChecks as $check) {
                $result = $this->performCheck($check, $transactionId, $agentId, $transactionData, $securityMetadata);
                $verificationResults['checks'][$check] = $result;

                // Stop on critical failures
                if (! $result['passed'] && $result['severity'] === 'critical') {
                    $verificationResults['status'] = 'rejected';
                    $verificationResults['reason'] = "Critical failure in {$check} check";
                    break;
                }
            }

            // Calculate overall verification status
            if (! isset($verificationResults['status'])) {
                $verificationResults = $this->calculateVerificationStatus($verificationResults);
            }

            // Calculate risk score
            $verificationResults['risk_score'] = $this->calculateRiskScore($verificationResults['checks']);
            $verificationResults['risk_level'] = $this->determineRiskLevel($verificationResults['risk_score']);

            // Store verification result
            $this->storeVerificationResult($transactionId, $verificationResults);

            // Log verification
            Log::info('Transaction verification completed', [
                'transaction_id' => $transactionId,
                'status'         => $verificationResults['status'],
                'risk_level'     => $verificationResults['risk_level'],
            ]);

            return $verificationResults;
        } catch (Exception $e) {
            Log::error('Transaction verification failed', [
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'error'          => $e->getMessage(),
            ]);

            return [
                'transaction_id' => $transactionId,
                'status'         => 'error',
                'error'          => $e->getMessage(),
                'timestamp'      => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Verify transaction integrity with hash validation.
     */
    public function verifyTransactionIntegrity(
        string $transactionId,
        array $transactionData,
        string $expectedHash
    ): bool {
        try {
            // Calculate transaction hash
            $actualHash = $this->calculateTransactionHash($transactionData);

            // Compare hashes
            $isValid = hash_equals($expectedHash, $actualHash);

            // Check for tampering indicators
            if ($isValid) {
                $isValid = $this->checkForTampering($transactionId, $transactionData);
            }

            Log::info('Transaction integrity check', [
                'transaction_id' => $transactionId,
                'is_valid'       => $isValid,
            ]);

            return $isValid;
        } catch (Exception $e) {
            Log::error('Transaction integrity verification failed', [
                'transaction_id' => $transactionId,
                'error'          => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify transaction compliance with regulatory requirements.
     */
    public function verifyCompliance(
        string $transactionId,
        string $agentId,
        array $transactionData
    ): array {
        try {
            $complianceChecks = [];

            // KYC/KYB verification
            $complianceChecks['kyc'] = $this->verifyKYCStatus($agentId);

            // Transaction limits
            $limitsResult = $this->verifyTransactionLimits(
                $agentId,
                $transactionData['amount'] ?? 0,
                $transactionData['currency'] ?? 'USD'
            );
            $complianceChecks['limits'] = [
                'passed'  => $limitsResult['within_limits'],
                'reason'  => $limitsResult['reason'] ?? null,
                'details' => $limitsResult,
            ];

            // Sanctions screening
            $complianceChecks['sanctions'] = $this->checkSanctionsList($agentId);

            // AML checks
            $complianceChecks['aml'] = $this->performAMLChecks($transactionData);

            // Regulatory reporting requirements
            $complianceChecks['reporting'] = $this->checkReportingRequirements(
                $transactionData['amount'] ?? 0,
                $transactionData['currency'] ?? 'USD'
            );

            $isCompliant = array_reduce($complianceChecks, function ($carry, $check) {
                return $carry && $check['passed'];
            }, true);

            return [
                'is_compliant'   => $isCompliant,
                'checks'         => $complianceChecks,
                'transaction_id' => $transactionId,
                'timestamp'      => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('Compliance verification failed', [
                'transaction_id' => $transactionId,
                'error'          => $e->getMessage(),
            ]);

            return [
                'is_compliant'   => false,
                'error'          => $e->getMessage(),
                'transaction_id' => $transactionId,
            ];
        }
    }

    /**
     * Perform velocity checks for fraud prevention.
     */
    public function performVelocityChecks(string $agentId, float $amount): array
    {
        try {
            $velocityLimits = [
                'hourly'  => ['limit' => 10000, 'count' => 10],
                'daily'   => ['limit' => 50000, 'count' => 50],
                'weekly'  => ['limit' => 200000, 'count' => 200],
                'monthly' => ['limit' => 500000, 'count' => 500],
            ];

            $violations = [];

            foreach ($velocityLimits as $period => $limits) {
                $stats = $this->getTransactionStats($agentId, $period);

                if ($stats['total_amount'] + $amount > $limits['limit']) {
                    $violations[] = [
                        'type'    => 'amount_limit',
                        'period'  => $period,
                        'current' => $stats['total_amount'],
                        'limit'   => $limits['limit'],
                    ];
                }

                if ($stats['count'] >= $limits['count']) {
                    $violations[] = [
                        'type'    => 'count_limit',
                        'period'  => $period,
                        'current' => $stats['count'],
                        'limit'   => $limits['count'],
                    ];
                }
            }

            return [
                'passed'     => empty($violations),
                'violations' => $violations,
                'agent_id'   => $agentId,
                'timestamp'  => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('Velocity check failed', [
                'agent_id' => $agentId,
                'error'    => $e->getMessage(),
            ]);

            return [
                'passed' => false,
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify multi-factor authentication for high-value transactions.
     */
    public function verifyMultiFactor(
        string $agentId,
        string $transactionId,
        array $factors
    ): array {
        try {
            $verifiedFactors = [];

            // Verify each authentication factor
            foreach ($factors as $factorType => $factorData) {
                $verifiedFactors[$factorType] = $this->verifyAuthFactor(
                    $agentId,
                    $factorType,
                    $factorData
                );
            }

            // Require at least 2 factors for high-value transactions
            $verifiedCount = count(array_filter($verifiedFactors, fn ($v) => $v['verified']));
            $isVerified = $verifiedCount >= 2;

            return [
                'verified'       => $isVerified,
                'factors'        => $verifiedFactors,
                'verified_count' => $verifiedCount,
                'required_count' => 2,
                'transaction_id' => $transactionId,
                'timestamp'      => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('Multi-factor verification failed', [
                'transaction_id' => $transactionId,
                'error'          => $e->getMessage(),
            ]);

            return [
                'verified' => false,
                'error'    => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform a specific verification check.
     */
    private function performCheck(
        string $checkType,
        string $transactionId,
        string $agentId,
        array $transactionData,
        array $securityMetadata
    ): array {
        return match ($checkType) {
            'signature'    => $this->verifySignatureCheck($transactionId, $agentId, $transactionData, $securityMetadata),
            'agent'        => $this->verifyAgentCheck($agentId),
            'limits'       => $this->verifyLimitsCheck($agentId, $transactionData['amount'] ?? 0),
            'velocity'     => $this->verifyVelocityCheck($agentId, $transactionData['amount'] ?? 0),
            'fraud'        => $this->verifyFraudCheck($transactionId, $agentId, $transactionData),
            'compliance'   => $this->verifyComplianceCheck($transactionId, $agentId, $transactionData),
            'encryption'   => $this->verifyEncryptionCheck($securityMetadata),
            'multi_factor' => $this->verifyMultiFactorCheck($agentId, $transactionId, $securityMetadata),
            default        => ['passed' => false, 'reason' => 'Unknown check type'],
        };
    }

    /**
     * Verify digital signature.
     */
    private function verifySignatureCheck(
        string $transactionId,
        string $agentId,
        array $transactionData,
        array $metadata
    ): array {
        if (! isset($metadata['signature'])) {
            return ['passed' => false, 'reason' => 'Missing signature', 'severity' => 'critical'];
        }

        $result = $this->signatureService->verifyAgentSignature(
            $transactionId,
            $agentId,
            $transactionData,
            $metadata['signature'],
            $metadata
        );

        return [
            'passed'   => $result['is_valid'],
            'reason'   => $result['reason'] ?? null,
            'severity' => $result['is_valid'] ? 'info' : 'critical',
        ];
    }

    /**
     * Verify agent status and eligibility.
     */
    private function verifyAgentCheck(string $agentId): array
    {
        $agent = Agent::where('agent_id', $agentId)->first();

        if (! $agent) {
            return ['passed' => false, 'reason' => 'Agent not found', 'severity' => 'critical'];
        }

        if ($agent->status !== 'active') {
            return ['passed' => false, 'reason' => 'Agent not active', 'severity' => 'high'];
        }

        if ($agent->is_suspended) {
            return ['passed' => false, 'reason' => 'Agent suspended', 'severity' => 'critical'];
        }

        return ['passed' => true, 'severity' => 'info'];
    }

    /**
     * Verify transaction limits.
     */
    private function verifyLimitsCheck(string $agentId, float $amount): array
    {
        $result = $this->verifyTransactionLimits($agentId, $amount, 'USD');

        return [
            'passed'   => $result['within_limits'],
            'reason'   => $result['reason'] ?? null,
            'severity' => $result['within_limits'] ? 'info' : 'high',
            'details'  => $result,
        ];
    }

    /**
     * Verify velocity limits.
     */
    private function verifyVelocityCheck(string $agentId, float $amount): array
    {
        $result = $this->performVelocityChecks($agentId, $amount);

        return [
            'passed'     => $result['passed'],
            'reason'     => ! $result['passed'] ? 'Velocity limit exceeded' : null,
            'severity'   => $result['passed'] ? 'info' : 'medium',
            'violations' => $result['violations'] ?? [],
        ];
    }

    /**
     * Verify fraud detection.
     */
    private function verifyFraudCheck(
        string $transactionId,
        string $agentId,
        array $transactionData
    ): array {
        $fraudAnalysis = $this->fraudService->analyzeTransaction(
            $transactionId,
            $agentId,
            $transactionData['amount'] ?? 0,
            $transactionData
        );

        $passed = $fraudAnalysis['decision'] !== 'reject';

        return [
            'passed'       => $passed,
            'reason'       => ! $passed ? 'High fraud risk detected' : null,
            'severity'     => $passed ? 'info' : 'high',
            'risk_score'   => $fraudAnalysis['risk_score'],
            'risk_factors' => $fraudAnalysis['risk_factors'] ?? [],
        ];
    }

    /**
     * Verify compliance requirements.
     */
    private function verifyComplianceCheck(
        string $transactionId,
        string $agentId,
        array $transactionData
    ): array {
        $complianceResult = $this->verifyCompliance($transactionId, $agentId, $transactionData);

        return [
            'passed'   => $complianceResult['is_compliant'],
            'reason'   => ! $complianceResult['is_compliant'] ? 'Compliance check failed' : null,
            'severity' => $complianceResult['is_compliant'] ? 'info' : 'critical',
            'details'  => $complianceResult['checks'] ?? [],
        ];
    }

    /**
     * Verify encryption status.
     */
    private function verifyEncryptionCheck(array $metadata): array
    {
        if (! isset($metadata['encrypted']) || ! $metadata['encrypted']) {
            return [
                'passed'   => false,
                'reason'   => 'Transaction not encrypted',
                'severity' => 'medium',
            ];
        }

        // Verify the encryption algorithm is supported
        $supportedCipher = $this->encryptionService->isCipherSupported($metadata['cipher'] ?? '');

        return [
            'passed'    => true,
            'cipher'    => $metadata['cipher'] ?? 'unknown',
            'supported' => $supportedCipher,
            'severity'  => 'info',
        ];
    }

    /**
     * Verify multi-factor authentication.
     */
    private function verifyMultiFactorCheck(
        string $agentId,
        string $transactionId,
        array $metadata
    ): array {
        if (! isset($metadata['auth_factors'])) {
            return [
                'passed'   => false,
                'reason'   => 'Multi-factor authentication required',
                'severity' => 'high',
            ];
        }

        $mfaResult = $this->verifyMultiFactor($agentId, $transactionId, $metadata['auth_factors']);

        return [
            'passed'           => $mfaResult['verified'],
            'reason'           => ! $mfaResult['verified'] ? 'Multi-factor authentication failed' : null,
            'severity'         => $mfaResult['verified'] ? 'info' : 'high',
            'verified_factors' => $mfaResult['verified_count'],
        ];
    }

    /**
     * Calculate overall verification status.
     */
    private function calculateVerificationStatus(array $results): array
    {
        $failedCritical = false;
        $failedHigh = false;
        $warnings = 0;

        foreach ($results['checks'] as $check) {
            if (! $check['passed']) {
                if ($check['severity'] === 'critical') {
                    $failedCritical = true;
                } elseif ($check['severity'] === 'high') {
                    $failedHigh = true;
                } elseif ($check['severity'] === 'medium') {
                    $warnings++;
                }
            }
        }

        if ($failedCritical) {
            $results['status'] = 'rejected';
            $results['reason'] = 'Critical verification failure';
        } elseif ($failedHigh) {
            $results['status'] = 'review_required';
            $results['reason'] = 'High severity issues detected';
        } elseif ($warnings > 2) {
            $results['status'] = 'review_required';
            $results['reason'] = 'Multiple warnings detected';
        } else {
            $results['status'] = 'approved';
        }

        return $results;
    }

    /**
     * Calculate risk score based on verification checks.
     */
    private function calculateRiskScore(array $checks): float
    {
        $baseScore = 0;
        $weights = [
            'signature'    => 25,
            'agent'        => 15,
            'limits'       => 10,
            'velocity'     => 15,
            'fraud'        => 20,
            'compliance'   => 10,
            'encryption'   => 3,
            'multi_factor' => 2,
        ];

        foreach ($checks as $checkType => $result) {
            if (! $result['passed']) {
                $weight = $weights[$checkType] ?? 5;
                $severityMultiplier = match ($result['severity'] ?? 'medium') {
                    'critical' => 2.0,
                    'high'     => 1.5,
                    'medium'   => 1.0,
                    'low'      => 0.5,
                    default    => 1.0,
                };
                $baseScore += $weight * $severityMultiplier;
            }
        }

        return min(100, $baseScore);
    }

    /**
     * Determine risk level from score.
     */
    private function determineRiskLevel(float $score): string
    {
        foreach (self::RISK_THRESHOLDS as $level => $threshold) {
            if ($score <= $threshold) {
                return $level;
            }
        }

        return 'critical';
    }

    /**
     * Calculate transaction hash for integrity verification.
     */
    private function calculateTransactionHash(array $data): string
    {
        ksort($data);
        $json = json_encode($data);

        return hash('sha256', $json !== false ? $json : '');
    }

    /**
     * Check for transaction tampering.
     */
    private function checkForTampering(string $transactionId, array $data): bool
    {
        // Check if transaction exists in database
        $transaction = AgentTransaction::where('transaction_id', $transactionId)->first();
        if (! $transaction) {
            return false;
        }

        // Compare key fields
        if (isset($data['amount']) && $transaction->amount != $data['amount']) {
            return false;
        }

        if (isset($data['recipient']) && $transaction->to_agent_id != $data['recipient']) {
            return false;
        }

        return true;
    }

    /**
     * Verify KYC status.
     */
    private function verifyKYCStatus(string $agentId): array
    {
        $agent = Agent::where('agent_id', $agentId)->first();

        return [
            'passed'      => $agent && $agent->kyc_verified,
            'status'      => $agent ? $agent->kyc_status : 'unknown',
            'verified_at' => $agent?->kyc_verified_at,
        ];
    }

    /**
     * Verify transaction limits.
     */
    private function verifyTransactionLimits(string $agentId, float $amount, string $currency): array
    {
        $agent = Agent::where('agent_id', $agentId)->first();

        if (! $agent) {
            return ['within_limits' => false, 'reason' => 'Agent not found'];
        }

        $limits = [
            'single_transaction' => $agent->single_transaction_limit ?? 10000,
            'daily'              => $agent->daily_limit ?? 50000,
            'monthly'            => $agent->monthly_limit ?? 200000,
        ];

        // Convert amount if needed
        $convertedAmount = $this->convertCurrency($amount, $currency, 'USD');

        if ($convertedAmount > $limits['single_transaction']) {
            return [
                'within_limits' => false,
                'reason'        => 'Exceeds single transaction limit',
                'limit'         => $limits['single_transaction'],
                'amount'        => $convertedAmount,
            ];
        }

        // Check daily limit
        $dailyTotal = $this->getTransactionStats($agentId, 'daily')['total_amount'];
        if ($dailyTotal + $convertedAmount > $limits['daily']) {
            return [
                'within_limits' => false,
                'reason'        => 'Exceeds daily limit',
                'limit'         => $limits['daily'],
                'current'       => $dailyTotal,
            ];
        }

        return ['within_limits' => true];
    }

    /**
     * Check sanctions list.
     */
    private function checkSanctionsList(string $agentId): array
    {
        // This would integrate with sanctions screening APIs
        // For now, return mock result
        return [
            'passed'        => true,
            'checked_lists' => ['OFAC', 'EU', 'UN'],
            'matches'       => [],
        ];
    }

    /**
     * Perform AML checks.
     */
    private function performAMLChecks(array $transactionData): array
    {
        $suspicious = false;
        $indicators = [];

        // Check for AML red flags
        if (($transactionData['amount'] ?? 0) > 9999) {
            $indicators[] = 'Large transaction amount';
        }

        if (isset($transactionData['is_cash']) && $transactionData['is_cash']) {
            $indicators[] = 'Cash transaction';
        }

        if (isset($transactionData['high_risk_country']) && $transactionData['high_risk_country']) {
            $indicators[] = 'High-risk jurisdiction';
            $suspicious = true;
        }

        return [
            'passed'       => ! $suspicious,
            'indicators'   => $indicators,
            'requires_sar' => count($indicators) > 2,
        ];
    }

    /**
     * Check reporting requirements.
     */
    private function checkReportingRequirements(float $amount, string $currency): array
    {
        $usdAmount = $this->convertCurrency($amount, $currency, 'USD');

        return [
            'passed'       => true,
            'ctr_required' => $usdAmount >= 10000,
            'sar_required' => false,
            'amount_usd'   => $usdAmount,
        ];
    }

    /**
     * Get transaction statistics for a period.
     */
    private function getTransactionStats(string $agentId, string $period): array
    {
        $startDate = match ($period) {
            'hourly'  => now()->subHour(),
            'daily'   => now()->subDay(),
            'weekly'  => now()->subWeek(),
            'monthly' => now()->subMonth(),
            default   => now()->subDay(),
        };

        $stats = DB::table('agent_transactions')
            ->where('from_agent_id', $agentId)
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as count, SUM(amount) as total_amount')
            ->first();

        return [
            'count'        => $stats->count ?? 0,
            'total_amount' => $stats->total_amount ?? 0,
        ];
    }

    /**
     * Verify authentication factor.
     */
    private function verifyAuthFactor(string $agentId, string $factorType, array $factorData): array
    {
        // This would integrate with actual authentication systems
        // For now, return mock verification
        return match ($factorType) {
            'password'     => ['verified' => true, 'type' => 'password'],
            'totp'         => ['verified' => true, 'type' => 'totp'],
            'biometric'    => ['verified' => true, 'type' => 'biometric'],
            'hardware_key' => ['verified' => true, 'type' => 'hardware_key'],
            default        => ['verified' => false, 'type' => $factorType],
        };
    }

    /**
     * Convert currency (simplified).
     */
    private function convertCurrency(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        // This would use real exchange rates
        // For now, use simplified conversion
        $rates = [
            'EUR' => 1.1,
            'GBP' => 1.3,
            'JPY' => 0.009,
        ];

        if ($from === 'USD') {
            return $amount / ($rates[$to] ?? 1);
        } elseif ($to === 'USD') {
            return $amount * ($rates[$from] ?? 1);
        }

        return $amount;
    }

    /**
     * Store verification result.
     */
    private function storeVerificationResult(string $transactionId, array $result): void
    {
        Cache::put(
            "verification:{$transactionId}",
            $result,
            now()->addDays(30)
        );
    }
}
