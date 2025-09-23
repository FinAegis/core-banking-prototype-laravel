<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Models\SecurityAuditLog;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Workflow\Activity;

class LogSecurityFailureActivity extends Activity
{
    public function execute(
        string $transactionId,
        string $agentId,
        string $reason,
        array $context = []
    ): array {
        try {
            DB::beginTransaction();

            // Log to database
            $auditLog = SecurityAuditLog::create([
                'event_type'     => 'security_failure',
                'severity'       => 'critical',
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'reason'         => $reason,
                'context'        => $context,
                'ip_address'     => request()->ip() ?? 'system',
                'user_agent'     => request()->userAgent() ?? 'system',
                'occurred_at'    => now(),
            ]);

            // Log to application logs with different severity levels
            $severity = $this->determineSeverity($reason, $context);

            match ($severity) {
                'critical' => Log::critical('Critical security failure detected', [
                    'transaction_id' => $transactionId,
                    'agent_id'       => $agentId,
                    'reason'         => $reason,
                    'context'        => $context,
                    'audit_log_id'   => $auditLog->id,
                ]),
                'high' => Log::error('Security failure detected', [
                    'transaction_id' => $transactionId,
                    'agent_id'       => $agentId,
                    'reason'         => $reason,
                    'context'        => $context,
                    'audit_log_id'   => $auditLog->id,
                ]),
                default => Log::warning('Security issue detected', [
                    'transaction_id' => $transactionId,
                    'agent_id'       => $agentId,
                    'reason'         => $reason,
                    'context'        => $context,
                    'audit_log_id'   => $auditLog->id,
                ]),
            };

            // Check if we need to trigger additional security measures
            $securityMeasures = $this->determineSecurityMeasures($reason, $context);

            if ($securityMeasures['suspend_agent']) {
                $this->suspendAgent($agentId, $reason);
            }

            if ($securityMeasures['block_transaction']) {
                $this->blockTransaction($transactionId);
            }

            if ($securityMeasures['notify_security_team']) {
                $this->notifySecurityTeam($transactionId, $agentId, $reason, $context);
            }

            DB::commit();

            return [
                'success'           => true,
                'audit_log_id'      => $auditLog->id,
                'severity'          => $severity,
                'security_measures' => $securityMeasures,
                'logged_at'         => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            DB::rollBack();

            // Even if database logging fails, try to log to file
            Log::emergency('Failed to log security failure - CRITICAL', [
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'reason'         => $reason,
                'error'          => $e->getMessage(),
                'context'        => $context,
            ]);

            return [
                'success'        => false,
                'error'          => $e->getMessage(),
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'failed_at'      => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Determine severity level based on failure reason and context.
     */
    private function determineSeverity(string $reason, array $context): string
    {
        // Critical severity triggers
        $criticalPatterns = [
            'signature.*invalid',
            'encryption.*failed',
            'authentication.*breach',
            'multiple.*failures',
            'suspicious.*pattern',
            'fraud.*detected',
        ];

        foreach ($criticalPatterns as $pattern) {
            if (preg_match("/{$pattern}/i", $reason)) {
                return 'critical';
            }
        }

        // Check context for high-risk indicators
        if (
            isset($context['fraud_analysis']['risk_score']) &&
            $context['fraud_analysis']['risk_score'] > 80
        ) {
            return 'critical';
        }

        if (
            isset($context['security_checks']) &&
            count(array_filter($context['security_checks'], fn ($check) => ! ($check['passed'] ?? false))) > 2
        ) {
            return 'high';
        }

        return 'medium';
    }

    /**
     * Determine what security measures to take.
     */
    private function determineSecurityMeasures(string $reason, array $context): array
    {
        $measures = [
            'suspend_agent'         => false,
            'block_transaction'     => true,
            'notify_security_team'  => false,
            'require_manual_review' => true,
        ];

        // Determine based on severity and patterns
        if (
            str_contains(strtolower($reason), 'fraud') ||
            str_contains(strtolower($reason), 'breach')
        ) {
            $measures['suspend_agent'] = true;
            $measures['notify_security_team'] = true;
        }

        if (
            isset($context['fraud_analysis']['risk_score']) &&
            $context['fraud_analysis']['risk_score'] > 90
        ) {
            $measures['suspend_agent'] = true;
            $measures['notify_security_team'] = true;
        }

        // Check for repeated failures
        if (isset($context['failure_count']) && $context['failure_count'] > 3) {
            $measures['suspend_agent'] = true;
        }

        return $measures;
    }

    /**
     * Suspend an agent due to security failure.
     */
    private function suspendAgent(string $agentId, string $reason): void
    {
        try {
            DB::table('agents')
                ->where('agent_id', $agentId)
                ->update([
                    'is_suspended'      => true,
                    'suspension_reason' => $reason,
                    'suspended_at'      => now(),
                ]);

            Log::warning('Agent suspended due to security failure', [
                'agent_id' => $agentId,
                'reason'   => $reason,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to suspend agent', [
                'agent_id' => $agentId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Block a transaction.
     */
    private function blockTransaction(string $transactionId): void
    {
        try {
            DB::table('agent_transactions')
                ->where('transaction_id', $transactionId)
                ->update([
                    'status'         => 'blocked',
                    'blocked_reason' => 'Security failure',
                    'blocked_at'     => now(),
                ]);

            Log::info('Transaction blocked due to security failure', [
                'transaction_id' => $transactionId,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to block transaction', [
                'transaction_id' => $transactionId,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify security team about the failure.
     */
    private function notifySecurityTeam(
        string $transactionId,
        string $agentId,
        string $reason,
        array $context
    ): void {
        try {
            // This would integrate with notification system
            // For now, just log a critical alert
            Log::critical('SECURITY ALERT - Immediate attention required', [
                'alert_type'     => 'security_failure',
                'transaction_id' => $transactionId,
                'agent_id'       => $agentId,
                'reason'         => $reason,
                'context'        => $context,
                'timestamp'      => now()->toIso8601String(),
            ]);

            // In production, this would:
            // - Send email/SMS to security team
            // - Create incident in security management system
            // - Trigger automated response workflows
        } catch (Exception $e) {
            Log::error('Failed to notify security team', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
