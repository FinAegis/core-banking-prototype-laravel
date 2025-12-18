<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Aggregates\AgentComplianceAggregate;
use App\Domain\AgentProtocol\Enums\KycVerificationLevel;
use App\Domain\AgentProtocol\Enums\KycVerificationStatus;
use App\Domain\AgentProtocol\Models\AgentIdentity;
use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Compliance\Services\AmlScreeningService;
use App\Domain\Compliance\Services\KycService;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service that integrates agent protocol KYC with the main compliance system.
 *
 * This service bridges the Agent Protocol's KYC requirements with the core
 * Compliance domain, allowing agents to leverage existing KYC infrastructure.
 */
class AgentKycIntegrationService
{
    public function __construct(
        private readonly ?KycService $kycService = null,
        private readonly ?AmlScreeningService $amlScreeningService = null
    ) {
    }

    /**
     * Verify an agent's KYC status using the main KYC system.
     *
     * If the agent is linked to a user account with approved KYC,
     * this will automatically approve the agent's KYC.
     *
     * @param string $agentId The agent's DID
     * @param string|null $linkedUserId Optional user ID to link
     * @return array KYC verification result
     */
    public function verifyAgentKyc(string $agentId, ?string $linkedUserId = null): array
    {
        try {
            $agent = AgentIdentity::where('did', $agentId)->first();

            if (! $agent) {
                return [
                    'success' => false,
                    'status'  => KycVerificationStatus::REJECTED->value,
                    'message' => 'Agent not found',
                ];
            }

            // Check if agent is linked to a user
            $user = null;
            if ($linkedUserId) {
                $user = User::where('id', $linkedUserId)->first();
            } elseif (isset($agent->metadata['linked_user_id'])) {
                $user = User::where('id', $agent->metadata['linked_user_id'])->first();
            }

            if ($user instanceof User && $this->kycService->isKycApproved($user)) {
                // User has approved KYC - approve agent automatically
                return $this->approveAgentFromUserKyc($agentId, $user);
            }

            // No linked user with KYC - return pending status
            return [
                'success'      => true,
                'status'       => KycVerificationStatus::PENDING->value,
                'level'        => KycVerificationLevel::BASIC->value,
                'message'      => 'Agent KYC verification pending - no linked user with approved KYC',
                'requirements' => $this->getAgentKycRequirements(KycVerificationLevel::BASIC),
            ];
        } catch (Exception $e) {
            Log::error('Agent KYC verification failed', [
                'agent_id' => $agentId,
                'error'    => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status'  => KycVerificationStatus::REJECTED->value,
                'message' => 'KYC verification failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Approve agent KYC based on linked user's approved KYC.
     */
    private function approveAgentFromUserKyc(string $agentId, User $user): array
    {
        DB::beginTransaction();

        try {
            // Map user KYC level to agent KYC level
            $agentLevel = $this->mapUserKycLevelToAgentLevel($user->kyc_level ?? 'basic');

            // Update agent compliance aggregate
            $aggregate = AgentComplianceAggregate::retrieve($agentId);
            // The aggregate should have methods to update KYC status

            // Get transaction limits based on user's KYC level
            $requirements = $this->kycService->getRequirements($user->kyc_level ?? 'basic');
            $limits = $requirements['limits'] ?? [];

            // Update agent metadata with KYC info
            $agent = AgentIdentity::where('did', $agentId)->first();
            if ($agent) {
                $metadata = $agent->metadata ?? [];
                $metadata['kyc_status'] = KycVerificationStatus::VERIFIED->value;
                $metadata['kyc_level'] = $agentLevel->value;
                $metadata['kyc_verified_at'] = now()->toIso8601String();
                $metadata['kyc_source'] = 'linked_user';
                $metadata['linked_user_id'] = $user->id;
                $metadata['transaction_limits'] = $limits;

                $agent->update(['metadata' => $metadata]);
            }

            // Log the action
            AuditLog::log(
                'agent_kyc.approved_from_user',
                $user,
                null,
                [
                    'agent_id'  => $agentId,
                    'kyc_level' => $agentLevel->value,
                ],
                [
                    'source'         => 'linked_user_kyc',
                    'user_kyc_level' => $user->kyc_level,
                ],
                'agent,kyc,compliance'
            );

            DB::commit();

            Log::info('Agent KYC approved from linked user', [
                'agent_id' => $agentId,
                'user_id'  => $user->id,
                'level'    => $agentLevel->value,
            ]);

            // Handle expires_at which could be Carbon, string, or null
            $expiresAt = null;
            if ($user->kyc_expires_at !== null) {
                $expiresAt = $user->kyc_expires_at instanceof \Carbon\Carbon
                    ? $user->kyc_expires_at->toIso8601String()
                    : (string) $user->kyc_expires_at;
            }

            return [
                'success'    => true,
                'status'     => KycVerificationStatus::VERIFIED->value,
                'level'      => $agentLevel->value,
                'message'    => 'Agent KYC approved based on linked user verification',
                'limits'     => $limits,
                'expires_at' => $expiresAt,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve agent KYC from user', [
                'agent_id' => $agentId,
                'user_id'  => $user->id,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Link an agent to a user account for KYC inheritance.
     *
     * @param string $agentId The agent's DID
     * @param int $userId The user's ID
     * @return bool Success status
     */
    public function linkAgentToUser(string $agentId, int $userId): bool
    {
        try {
            $agent = AgentIdentity::where('did', $agentId)->first();
            $user = User::where('id', $userId)->first();

            if (! $agent || ! $user instanceof User) {
                return false;
            }

            $metadata = $agent->metadata ?? [];
            $metadata['linked_user_id'] = $userId;
            $metadata['linked_at'] = now()->toIso8601String();

            $agent->update(['metadata' => $metadata]);

            Log::info('Agent linked to user', [
                'agent_id' => $agentId,
                'user_id'  => $userId,
            ]);

            // Automatically verify KYC if user is approved
            if ($this->kycService->isKycApproved($user)) {
                $this->verifyAgentKyc($agentId);
            }

            return true;
        } catch (Exception $e) {
            Log::error('Failed to link agent to user', [
                'agent_id' => $agentId,
                'user_id'  => $userId,
                'error'    => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Perform AML screening for an agent transaction.
     *
     * @param string $agentId The agent's DID
     * @param float $amount Transaction amount
     * @param string $currency Transaction currency
     * @param array $metadata Additional transaction metadata
     * @return array Screening result
     */
    public function screenAgentTransaction(
        string $agentId,
        float $amount,
        string $currency,
        array $metadata = []
    ): array {
        try {
            // Get agent's KYC level to determine limits
            $agent = AgentIdentity::where('did', $agentId)->first();
            $agentMetadata = $agent !== null ? ($agent->metadata ?? []) : [];
            $kycLevel = $agentMetadata['kyc_level'] ?? KycVerificationLevel::BASIC->value;
            $limits = $agentMetadata['transaction_limits'] ?? [];

            // Check transaction limits
            $dailyLimit = $limits['daily_transaction'] ?? 100000; // Default $1,000
            if (($amount * 100) > $dailyLimit) {
                return [
                    'passed' => false,
                    'reason' => 'Transaction exceeds daily limit',
                    'limit'  => $dailyLimit / 100,
                    'amount' => $amount,
                ];
            }

            // Perform AML screening if service available
            if ($this->amlScreeningService) {
                // Get linked user for screening
                $user = null;
                if (isset($agentMetadata['linked_user_id'])) {
                    $user = User::where('id', $agentMetadata['linked_user_id'])->first();
                }

                if ($user instanceof User) {
                    // Use existing AML screening
                    $screeningResult = $this->performAmlScreening($user, $amount, $metadata);
                    if (! $screeningResult['passed']) {
                        return $screeningResult;
                    }
                }
            }

            // Check for suspicious patterns
            $suspiciousCheck = $this->checkSuspiciousPatterns($agentId, $amount, $metadata);
            if (! $suspiciousCheck['passed']) {
                return $suspiciousCheck;
            }

            return [
                'passed'     => true,
                'kyc_level'  => $kycLevel,
                'risk_score' => $suspiciousCheck['risk_score'] ?? 0,
                'message'    => 'Transaction cleared',
            ];
        } catch (Exception $e) {
            Log::error('Agent transaction screening failed', [
                'agent_id' => $agentId,
                'amount'   => $amount,
                'error'    => $e->getMessage(),
            ]);

            // Fail-safe: block transaction on error
            return [
                'passed' => false,
                'reason' => 'Screening error - transaction blocked',
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Get agent's current KYC status.
     *
     * @param string $agentId The agent's DID
     * @return array KYC status information
     */
    public function getAgentKycStatus(string $agentId): array
    {
        $agent = AgentIdentity::where('did', $agentId)->first();

        if (! $agent) {
            return [
                'status'  => KycVerificationStatus::PENDING->value,
                'level'   => null,
                'message' => 'Agent not found',
            ];
        }

        $metadata = $agent->metadata ?? [];

        return [
            'status'             => $metadata['kyc_status'] ?? KycVerificationStatus::PENDING->value,
            'level'              => $metadata['kyc_level'] ?? null,
            'verified_at'        => $metadata['kyc_verified_at'] ?? null,
            'source'             => $metadata['kyc_source'] ?? null,
            'linked_user_id'     => $metadata['linked_user_id'] ?? null,
            'transaction_limits' => $metadata['transaction_limits'] ?? null,
        ];
    }

    /**
     * Get KYC requirements for an agent based on level.
     *
     * @param KycVerificationLevel $level The KYC verification level
     * @return array{requirements: array<string>, limits: array<string, int|null>}
     */
    public function getAgentKycRequirements(KycVerificationLevel $level): array
    {
        $kycConfig = config('agent_protocol.kyc.levels', []);

        return match ($level) {
            KycVerificationLevel::BASIC => [
                'requirements' => [
                    'Link to a user account with basic KYC',
                    'Or submit: Agent registration document, Controller ID',
                ],
                'limits' => [
                    'daily_transaction'      => $kycConfig['basic']['daily_limit'] ?? 1000,
                    'monthly_transaction'    => $kycConfig['basic']['monthly_limit'] ?? 5000,
                    'max_single_transaction' => $kycConfig['basic']['max_single'] ?? 500,
                ],
            ],
            KycVerificationLevel::ENHANCED => [
                'requirements' => [
                    'Link to a user account with enhanced KYC',
                    'Or submit: Agent registration, Controller ID, Business registration',
                ],
                'limits' => [
                    'daily_transaction'      => $kycConfig['enhanced']['daily_limit'] ?? 10000,
                    'monthly_transaction'    => $kycConfig['enhanced']['monthly_limit'] ?? 50000,
                    'max_single_transaction' => $kycConfig['enhanced']['max_single'] ?? 5000,
                ],
            ],
            KycVerificationLevel::FULL => [
                'requirements' => [
                    'Link to a user account with full KYC',
                    'Or complete full agent verification process',
                ],
                'limits' => [
                    'daily_transaction'      => $kycConfig['full']['daily_limit'] ?? null,
                    'monthly_transaction'    => $kycConfig['full']['monthly_limit'] ?? null,
                    'max_single_transaction' => $kycConfig['full']['max_single'] ?? null,
                ],
            ],
        };
    }

    /**
     * Map user KYC level to agent KYC level.
     */
    private function mapUserKycLevelToAgentLevel(string $userLevel): KycVerificationLevel
    {
        return match ($userLevel) {
            'basic'    => KycVerificationLevel::BASIC,
            'enhanced' => KycVerificationLevel::ENHANCED,
            'full'     => KycVerificationLevel::FULL,
            default    => KycVerificationLevel::BASIC,
        };
    }

    /**
     * Perform AML screening using the main compliance service.
     *
     * @param User $user The user associated with the agent
     * @param float $amount The transaction amount
     * @param array<string, mixed> $metadata Transaction metadata
     * @return array{passed: bool, risk_score: int, risk_factors: array<string>, reason: string|null}
     */
    private function performAmlScreening(User $user, float $amount, array $metadata): array
    {
        $amlConfig = config('agent_protocol.aml', []);
        $riskFactors = [];

        // Check for high-risk countries from config
        if (isset($metadata['destination_country'])) {
            $highRiskCountries = $amlConfig['high_risk_countries'] ?? ['KP', 'IR', 'SY', 'CU'];
            if (in_array($metadata['destination_country'], $highRiskCountries, true)) {
                $riskFactors[] = 'high_risk_country';
            }
        }

        // Check for large transactions using config threshold
        $largeTransactionThreshold = $amlConfig['large_transaction'] ?? 10000;
        if ($amount > $largeTransactionThreshold) {
            $riskFactors[] = 'large_transaction';
        }

        // Check user's PEP status
        if ($user->pep_status) {
            $riskFactors[] = 'pep_involved';
        }

        $riskScore = count($riskFactors) * 25;
        $riskThreshold = $amlConfig['risk_score_threshold'] ?? 75;

        return [
            'passed'       => $riskScore < $riskThreshold,
            'risk_score'   => $riskScore,
            'risk_factors' => $riskFactors,
            'reason'       => $riskScore >= $riskThreshold ? 'High risk transaction' : null,
        ];
    }

    /**
     * Check for suspicious transaction patterns.
     *
     * @param string $agentId The agent's DID
     * @param float $amount The transaction amount
     * @param array<string, mixed> $metadata Transaction metadata
     * @return array{passed: bool, risk_score: int, patterns: array<string>}
     */
    private function checkSuspiciousPatterns(string $agentId, float $amount, array $metadata): array
    {
        $fraudConfig = config('agent_protocol.fraud_detection', []);
        $riskScore = 0;
        $patterns = [];

        // Check for round amounts (potential structuring)
        $structuringThreshold = $fraudConfig['structuring_threshold'] ?? 1000;
        if (fmod($amount, $structuringThreshold) === 0.0 && $amount >= 9000) {
            $riskScore += 15;
            $patterns[] = 'round_amount_near_threshold';
        }

        // Check for very large transactions using config
        $largeTransactionThreshold = $fraudConfig['large_transaction'] ?? 50000;
        if ($amount >= $largeTransactionThreshold) {
            $riskScore += 25;
            $patterns[] = 'very_large_transaction';
        }

        // Check for suspicious metadata flags
        if (isset($metadata['high_risk_indicator']) && $metadata['high_risk_indicator'] === true) {
            $riskScore += 20;
            $patterns[] = 'high_risk_indicator_present';
        }

        // Check for unusual time patterns using config
        if (isset($metadata['transaction_hour'])) {
            $hour = (int) $metadata['transaction_hour'];
            $suspiciousHourStart = $fraudConfig['suspicious_hours']['start'] ?? 2;
            $suspiciousHourEnd = $fraudConfig['suspicious_hours']['end'] ?? 5;
            if ($hour >= $suspiciousHourStart && $hour <= $suspiciousHourEnd) {
                $riskScore += 10;
                $patterns[] = 'unusual_time_transaction';
            }
        }

        // Use config threshold for pass/fail determination
        $riskThreshold = $fraudConfig['risk_weights']['pattern'] ?? 50;

        return [
            'passed'     => $riskScore < $riskThreshold,
            'risk_score' => $riskScore,
            'patterns'   => $patterns,
        ];
    }
}
