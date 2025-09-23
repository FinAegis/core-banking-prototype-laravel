<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows;

use App\Domain\AgentProtocol\Aggregates\TransactionSecurityAggregate;
use App\Domain\AgentProtocol\Workflows\Activities\CheckFraudActivity;
use App\Domain\AgentProtocol\Workflows\Activities\EncryptTransactionActivity;
use App\Domain\AgentProtocol\Workflows\Activities\NotifySecurityEventActivity;
use App\Domain\AgentProtocol\Workflows\Activities\SignTransactionActivity;
use App\Domain\AgentProtocol\Workflows\Activities\VerifySignatureActivity;
use Carbon\CarbonInterval;
use Exception;
use Generator;
use Workflow\Activity;
use Workflow\Workflow;

class TransactionSecurityWorkflow extends Workflow
{
    private array $securityChecks = [];

    private array $fraudAnalysis = [];

    private string $finalStatus = 'pending';

    public function secureTransaction(
        string $transactionId,
        string $agentId,
        array $transactionData,
        string $securityLevel = 'standard'
    ): Generator {
        try {
            // Step 1: Initialize security aggregate
            $securityId = $this->generateSecurityId($transactionId);

            $aggregate = TransactionSecurityAggregate::initializeSecurity(
                securityId: $securityId,
                transactionId: $transactionId,
                agentId: $agentId,
                securityLevel: $securityLevel,
                metadata: ['workflow_id' => $this->workflowId()]
            );
            $aggregate->persist();

            // Step 2: Sign transaction
            $signatureResult = yield Activity::make(
                SignTransactionActivity::class,
                $transactionId,
                $transactionData,
                $agentId
            )->withTimeout(CarbonInterval::seconds(10));

            $this->securityChecks['signature'] = $signatureResult;

            // Update aggregate with signature
            $aggregate = TransactionSecurityAggregate::retrieve($securityId);
            $aggregate->signTransaction(
                signatureData: $signatureResult['signature'],
                signatureMethod: $signatureResult['algorithm'],
                publicKey: $signatureResult['public_key'],
                metadata: $signatureResult
            );
            $aggregate->persist();

            // Step 3: Encrypt if required
            $encryptionResult = null;
            if (in_array($securityLevel, ['enhanced', 'maximum'])) {
                $encryptionResult = yield Activity::make(
                    EncryptTransactionActivity::class,
                    $transactionData,
                    $securityId
                )->withTimeout(CarbonInterval::seconds(10));

                $this->securityChecks['encryption'] = $encryptionResult;

                // Update aggregate with encryption
                $aggregate = TransactionSecurityAggregate::retrieve($securityId);
                $aggregate->encryptTransaction(
                    encryptedData: $encryptionResult['encrypted_data'],
                    encryptionMethod: $encryptionResult['cipher'],
                    keyId: $encryptionResult['key_id'],
                    metadata: $encryptionResult
                );
                $aggregate->persist();
            }

            // Step 4: Verify signature
            $verificationResult = yield Activity::make(
                VerifySignatureActivity::class,
                $transactionData,
                $signatureResult['signature'],
                $signatureResult['public_key'],
                $signatureResult['algorithm']
            )->withTimeout(CarbonInterval::seconds(5));

            $this->securityChecks['verification'] = $verificationResult;

            // Update aggregate with verification
            $aggregate = TransactionSecurityAggregate::retrieve($securityId);
            $aggregate->verifyTransaction(
                signatureValid: $verificationResult['is_valid'],
                encryptionValid: $encryptionResult ? true : true, // Simplified for now
                verificationDetails: $verificationResult,
                metadata: ['verified_at' => now()->toIso8601String()]
            );
            $aggregate->persist();

            // Step 5: Fraud detection
            $fraudResult = yield Activity::make(
                CheckFraudActivity::class,
                $transactionId,
                $agentId,
                $transactionData['amount'] ?? 0,
                $transactionData
            )->withTimeout(CarbonInterval::seconds(15));

            $this->fraudAnalysis = $fraudResult;

            // Update aggregate with fraud check
            $aggregate = TransactionSecurityAggregate::retrieve($securityId);
            $aggregate->checkForFraud(
                riskScore: $fraudResult['risk_score'],
                riskFactors: $fraudResult['risk_factors'],
                decision: $fraudResult['decision'],
                metadata: $fraudResult
            );
            $aggregate->persist();

            // Step 6: Determine final status
            $this->finalStatus = $this->determineFinalStatus(
                $verificationResult['is_valid'],
                $fraudResult['decision']
            );

            // Step 7: Notify if suspicious or rejected
            if ($this->finalStatus !== 'approved') {
                yield Activity::make(
                    NotifySecurityEventActivity::class,
                    $transactionId,
                    $agentId,
                    $this->finalStatus,
                    [
                        'fraud_analysis'  => $this->fraudAnalysis,
                        'security_checks' => $this->securityChecks,
                    ]
                )->withTimeout(CarbonInterval::seconds(5));
            }

            return [
                'success'         => $this->finalStatus === 'approved',
                'transaction_id'  => $transactionId,
                'security_id'     => $securityId,
                'security_level'  => $securityLevel,
                'status'          => $this->finalStatus,
                'signature'       => $signatureResult['signature'],
                'encrypted'       => $encryptionResult !== null,
                'verified'        => $verificationResult['is_valid'],
                'fraud_analysis'  => $this->fraudAnalysis,
                'security_checks' => $this->securityChecks,
                'timestamp'       => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            // Handle failure with compensation
            yield $this->compensateSecurityFailure($transactionId, $agentId, $e->getMessage());

            return [
                'success'        => false,
                'transaction_id' => $transactionId,
                'error'          => $e->getMessage(),
                'status'         => 'failed',
                'timestamp'      => now()->toIso8601String(),
            ];
        }
    }

    public function verifyAndDecrypt(
        string $securityId,
        string $encryptedData,
        array $metadata
    ): Generator {
        try {
            $aggregate = TransactionSecurityAggregate::retrieve($securityId);

            if (! $aggregate->getSecurityId()) {
                throw new Exception("Security record not found: {$securityId}");
            }

            // Step 1: Verify signature
            $verificationResult = yield Activity::make(
                VerifySignatureActivity::class,
                ['encrypted_data' => $encryptedData],
                $metadata['signature'] ?? '',
                $metadata['public_key'] ?? '',
                $metadata['algorithm'] ?? 'RS256'
            )->withTimeout(CarbonInterval::seconds(5));

            if (! $verificationResult['is_valid']) {
                throw new Exception('Signature verification failed');
            }

            // Step 2: Decrypt if encrypted
            $decryptedData = null;
            if ($aggregate->requiresEncryption()) {
                $decryptResult = yield Activity::make(
                    'App\\Domain\\AgentProtocol\\Workflows\\Activities\\DecryptTransactionActivity',
                    $encryptedData,
                    $metadata['key_id'] ?? '',
                    $metadata['cipher'] ?? 'AES-256-GCM',
                    $metadata
                )->withTimeout(CarbonInterval::seconds(10));

                $decryptedData = $decryptResult['decrypted_data'];
            }

            return [
                'success'        => true,
                'security_id'    => $securityId,
                'verified'       => true,
                'decrypted_data' => $decryptedData,
                'timestamp'      => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            return [
                'success'     => false,
                'security_id' => $securityId,
                'error'       => $e->getMessage(),
                'timestamp'   => now()->toIso8601String(),
            ];
        }
    }

    public function performSecurityAudit(
        string $agentId,
        array $options = []
    ): Generator {
        try {
            $auditResults = [];

            // Audit recent transactions
            $recentTransactions = yield Activity::make(
                'App\\Domain\\AgentProtocol\\Workflows\\Activities\\GetRecentTransactionsActivity',
                $agentId,
                $options['days'] ?? 30
            )->withTimeout(CarbonInterval::seconds(30));

            // Analyze security patterns
            foreach ($recentTransactions as $transaction) {
                $fraudCheck = yield Activity::make(
                    CheckFraudActivity::class,
                    $transaction['id'],
                    $agentId,
                    $transaction['amount'],
                    $transaction
                )->withTimeout(CarbonInterval::seconds(10));

                if ($fraudCheck['risk_score'] > 60) {
                    $auditResults['suspicious_transactions'][] = [
                        'transaction_id' => $transaction['id'],
                        'risk_score'     => $fraudCheck['risk_score'],
                        'risk_factors'   => $fraudCheck['risk_factors'],
                    ];
                }
            }

            // Generate audit report
            $auditResults['total_transactions'] = count($recentTransactions);
            $auditResults['suspicious_count'] = count($auditResults['suspicious_transactions'] ?? []);
            $auditResults['audit_date'] = now()->toIso8601String();
            $auditResults['agent_id'] = $agentId;

            // Notify if issues found
            if ($auditResults['suspicious_count'] > 0) {
                yield Activity::make(
                    NotifySecurityEventActivity::class,
                    'audit_' . uniqid(),
                    $agentId,
                    'audit_alert',
                    $auditResults
                )->withTimeout(CarbonInterval::seconds(5));
            }

            return [
                'success'       => true,
                'agent_id'      => $agentId,
                'audit_results' => $auditResults,
                'timestamp'     => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            return [
                'success'   => false,
                'agent_id'  => $agentId,
                'error'     => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ];
        }
    }

    protected function compensateSecurityFailure(
        string $transactionId,
        string $agentId,
        string $reason
    ): Generator {
        try {
            // Log security failure
            yield Activity::make(
                'App\\Domain\\AgentProtocol\\Workflows\\Activities\\LogSecurityFailureActivity',
                $transactionId,
                $agentId,
                $reason,
                [
                    'security_checks' => $this->securityChecks,
                    'fraud_analysis'  => $this->fraudAnalysis,
                    'timestamp'       => now()->toIso8601String(),
                ]
            )->withTimeout(CarbonInterval::seconds(5));

            // Notify security team
            yield Activity::make(
                NotifySecurityEventActivity::class,
                $transactionId,
                $agentId,
                'security_failure',
                ['reason' => $reason]
            )->withTimeout(CarbonInterval::seconds(5));
        } catch (Exception $e) {
            // Log compensation failure
            error_log("Security compensation failed for transaction {$transactionId}: " . $e->getMessage());
        }
    }

    private function generateSecurityId(string $transactionId): string
    {
        return "security_{$transactionId}_" . uniqid();
    }

    private function determineFinalStatus(bool $verified, string $fraudDecision): string
    {
        if (! $verified) {
            return 'rejected';
        }

        return match ($fraudDecision) {
            'approve' => 'approved',
            'reject'  => 'rejected',
            'review'  => 'review_required',
            default   => 'pending',
        };
    }

    private function workflowId(): string
    {
        return 'workflow_' . uniqid();
    }
}
