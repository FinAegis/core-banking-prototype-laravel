<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\Compliance\Services\ComplianceAlertService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Workflow\Activity;

class NotifyComplianceOfficerActivity extends Activity
{
    /**
     * Notify compliance officer about manual review requirement.
     */
    public function execute(
        string $agentId,
        string $reason,
        int $riskScore,
        array $verificationResults
    ): array {
        // Log the notification
        Log::warning('Agent KYC requires manual review', [
            'agent_id'             => $agentId,
            'reason'               => $reason,
            'risk_score'           => $riskScore,
            'verification_results' => $verificationResults,
        ]);

        // Create compliance alert
        $alertService = app(ComplianceAlertService::class);
        $alertId = $alertService->createAlert(
            type: 'kyc_manual_review',
            severity: $riskScore > 80 ? 'critical' : 'high',
            entityType: 'agent',
            entityId: $agentId,
            description: "KYC manual review required: $reason",
            details: [
                'risk_score'           => $riskScore,
                'verification_results' => $verificationResults,
                'timestamp'            => now()->toIso8601String(),
            ]
        );

        // In production, send email notification
        // Mail::to(config('compliance.officer_email'))->send(new KycReviewRequired($agentId, $reason));

        return [
            'notified'             => true,
            'alert_id'             => $alertId,
            'notification_sent_at' => now()->toIso8601String(),
        ];
    }
}
