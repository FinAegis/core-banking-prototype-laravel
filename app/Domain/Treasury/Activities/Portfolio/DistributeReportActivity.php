<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities\Portfolio;

use App\Domain\Treasury\Events\Portfolio\ReportDistributed;
use Exception;
use Log;
use RuntimeException;
use Workflow\Activity;

class DistributeReportActivity extends Activity
{
    public function execute(array $input): array
    {
        $portfolioId = $input['portfolio_id'];
        $reportId = $input['report_id'];
        $generatedReport = $input['generated_report'];
        $recipients = $input['recipients'] ?? [];
        $distribution = $input['distribution'] ?? [];
        $notificationType = $input['notification_type'] ?? 'report';

        try {
            Log::info('Distributing performance report', [
                'portfolio_id' => $portfolioId,
                'report_id'    => $reportId,
                'format'       => $generatedReport['format'] ?? 'unknown',
                'recipients'   => count($recipients),
                'distribution' => array_keys(array_filter($distribution)),
            ]);

            $distributionResults = [];

            // Email distribution
            if ($distribution['email'] ?? false) {
                $emailResults = $this->distributeViaEmail($reportId, $generatedReport, $recipients, $notificationType);
                $distributionResults['email'] = $emailResults;
            }

            // Dashboard distribution
            if ($distribution['dashboard'] ?? false) {
                $dashboardResults = $this->distributeToDashboard($portfolioId, $reportId, $generatedReport, $notificationType);
                $distributionResults['dashboard'] = $dashboardResults;
            }

            // Archive distribution
            if ($distribution['archive'] ?? false) {
                $archiveResults = $this->archiveReport($portfolioId, $reportId, $generatedReport);
                $distributionResults['archive'] = $archiveResults;
            }

            // API webhook distribution
            if ($distribution['api_webhook'] ?? false) {
                $webhookResults = $this->distributeViaWebhook($portfolioId, $reportId, $generatedReport, $recipients);
                $distributionResults['webhook'] = $webhookResults;
            }

            // Slack/Teams notification (if configured)
            if ($distribution['team_chat'] ?? false) {
                $chatResults = $this->distributeViaTeamChat($portfolioId, $reportId, $generatedReport, $notificationType);
                $distributionResults['team_chat'] = $chatResults;
            }

            // SMS notifications for critical reports
            if ($distribution['sms'] ?? false) {
                $smsResults = $this->distributeViaSms($reportId, $recipients, $notificationType);
                $distributionResults['sms'] = $smsResults;
            }

            // Calculate overall distribution success
            $overallResults = $this->calculateDistributionSuccess($distributionResults);

            // Dispatch distribution completion event
            event(new ReportDistributed(
                $portfolioId,
                $reportId,
                $distributionResults,
                $recipients,
                [
                    'distributed_at'       => now()->toISOString(),
                    'total_recipients'     => count($recipients),
                    'successful_channels'  => $overallResults['successful_channels'],
                    'failed_channels'      => $overallResults['failed_channels'],
                    'distribution_methods' => array_keys(array_filter($distribution)),
                ]
            ));

            Log::info('Report distribution completed', [
                'portfolio_id'        => $portfolioId,
                'report_id'           => $reportId,
                'total_recipients'    => count($recipients),
                'successful_channels' => $overallResults['successful_channels'],
                'success_rate'        => $overallResults['success_rate'],
            ]);

            return [
                'success'               => $overallResults['overall_success'],
                'portfolio_id'          => $portfolioId,
                'report_id'             => $reportId,
                'distributed_at'        => now()->toISOString(),
                'distribution_results'  => $distributionResults,
                'successful_deliveries' => $overallResults['successful_deliveries'],
                'failed_deliveries'     => $overallResults['failed_deliveries'],
                'summary'               => [
                    'total_channels'      => count(array_filter($distribution)),
                    'successful_channels' => $overallResults['successful_channels'],
                    'failed_channels'     => $overallResults['failed_channels'],
                    'success_rate'        => $overallResults['success_rate'],
                    'total_recipients'    => count($recipients),
                ],
            ];
        } catch (Exception $e) {
            Log::error('Report distribution failed', [
                'portfolio_id' => $portfolioId,
                'report_id'    => $reportId,
                'error'        => $e->getMessage(),
            ]);

            throw new RuntimeException(
                "Failed to distribute report for portfolio {$portfolioId}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Distribute report via email to recipients.
     */
    private function distributeViaEmail(string $reportId, array $report, array $recipients, string $notificationType): array
    {
        $emailResults = [];

        foreach ($recipients as $recipient) {
            try {
                $recipientEmails = $this->getRecipientEmails($recipient);

                foreach ($recipientEmails as $email) {
                    $result = $this->sendReportEmail($email, $reportId, $report, $notificationType, $recipient);
                    $emailResults[] = $result;
                }
            } catch (Exception $e) {
                Log::warning('Failed to send email to recipient', [
                    'recipient' => $recipient,
                    'report_id' => $reportId,
                    'error'     => $e->getMessage(),
                ]);

                $emailResults[] = [
                    'recipient' => $recipient,
                    'email'     => 'unknown',
                    'success'   => false,
                    'error'     => $e->getMessage(),
                    'sent_at'   => now()->toISOString(),
                ];
            }
        }

        return [
            'method'     => 'email',
            'attempted'  => count($emailResults),
            'successful' => count(array_filter($emailResults, fn ($r) => $r['success'])),
            'failed'     => count(array_filter($emailResults, fn ($r) => ! $r['success'])),
            'results'    => $emailResults,
        ];
    }

    /**
     * Distribute report to dashboard/UI.
     */
    private function distributeToDashboard(string $portfolioId, string $reportId, array $report, string $notificationType): array
    {
        try {
            // In a real implementation, this would:
            // 1. Update dashboard notifications
            // 2. Create UI alerts/banners
            // 3. Update report listings
            // 4. Send real-time notifications via WebSocket/Pusher

            $dashboardData = [
                'portfolio_id'      => $portfolioId,
                'report_id'         => $reportId,
                'notification_type' => $notificationType,
                'report_info'       => [
                    'file_name' => $report['file_name'] ?? 'report.pdf',
                    'file_size' => $report['file_size'] ?? 0,
                    'format'    => $report['format'] ?? 'pdf',
                ],
                'published_at' => now()->toISOString(),
            ];

            // Simulate dashboard update
            Log::info('Dashboard updated with report', $dashboardData);

            return [
                'method'     => 'dashboard',
                'success'    => true,
                'updated_at' => now()->toISOString(),
                'data'       => $dashboardData,
            ];
        } catch (Exception $e) {
            return [
                'method'  => 'dashboard',
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Archive report for long-term storage.
     */
    private function archiveReport(string $portfolioId, string $reportId, array $report): array
    {
        try {
            $archivePath = $this->createArchivePath($portfolioId, $reportId);
            $sourceFile = $report['file_path'] ?? null;

            if (! $sourceFile || ! file_exists($sourceFile)) {
                throw new RuntimeException('Source report file not found');
            }

            // Ensure archive directory exists
            $archiveDir = dirname($archivePath);
            if (! is_dir($archiveDir)) {
                mkdir($archiveDir, 0755, true);
            }

            // Copy report to archive location
            copy($sourceFile, $archivePath);

            // Create archive metadata
            $metadata = [
                'portfolio_id'     => $portfolioId,
                'report_id'        => $reportId,
                'original_file'    => $sourceFile,
                'archive_file'     => $archivePath,
                'archived_at'      => now()->toISOString(),
                'file_size'        => filesize($archivePath),
                'retention_period' => '7 years', // Regulatory requirement
            ];

            file_put_contents($archivePath . '.meta.json', json_encode($metadata, JSON_PRETTY_PRINT));

            return [
                'method'       => 'archive',
                'success'      => true,
                'archive_path' => $archivePath,
                'metadata'     => $metadata,
            ];
        } catch (Exception $e) {
            return [
                'method'  => 'archive',
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Distribute report via API webhook.
     */
    private function distributeViaWebhook(string $portfolioId, string $reportId, array $report, array $recipients): array
    {
        $webhookResults = [];

        // Get webhook URLs from configuration or recipient preferences
        $webhookUrls = $this->getWebhookUrls($recipients);

        foreach ($webhookUrls as $url => $config) {
            try {
                $payload = [
                    'event_type'   => 'report.generated',
                    'portfolio_id' => $portfolioId,
                    'report_id'    => $reportId,
                    'report_info'  => [
                        'format'       => $report['format'] ?? 'pdf',
                        'file_size'    => $report['file_size'] ?? 0,
                        'generated_at' => $report['generated_at'] ?? now()->toISOString(),
                        'download_url' => $report['download_url'] ?? null,
                    ],
                    'timestamp' => now()->toISOString(),
                ];

                $result = $this->sendWebhook($url, $payload, $config);
                $webhookResults[] = $result;
            } catch (Exception $e) {
                $webhookResults[] = [
                    'url'     => $url,
                    'success' => false,
                    'error'   => $e->getMessage(),
                    'sent_at' => now()->toISOString(),
                ];
            }
        }

        return [
            'method'     => 'webhook',
            'attempted'  => count($webhookResults),
            'successful' => count(array_filter($webhookResults, fn ($r) => $r['success'])),
            'failed'     => count(array_filter($webhookResults, fn ($r) => ! $r['success'])),
            'results'    => $webhookResults,
        ];
    }

    /**
     * Distribute via team chat (Slack, Teams, etc.).
     */
    private function distributeViaTeamChat(string $portfolioId, string $reportId, array $report, string $notificationType): array
    {
        try {
            $message = $this->buildChatMessage($portfolioId, $reportId, $report, $notificationType);

            // In a real implementation, would integrate with Slack/Teams APIs
            Log::info('Team chat notification sent', [
                'portfolio_id' => $portfolioId,
                'report_id'    => $reportId,
                'message'      => $message,
            ]);

            return [
                'method'  => 'team_chat',
                'success' => true,
                'message' => $message,
                'sent_at' => now()->toISOString(),
            ];
        } catch (Exception $e) {
            return [
                'method'  => 'team_chat',
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Distribute via SMS for critical notifications.
     */
    private function distributeViaSms(string $reportId, array $recipients, string $notificationType): array
    {
        $smsResults = [];

        // Only send SMS for critical notifications
        if (! in_array($notificationType, ['failure', 'critical_alert'])) {
            return [
                'method'    => 'sms',
                'skipped'   => 'Not critical notification',
                'attempted' => 0,
            ];
        }

        foreach ($recipients as $recipient) {
            try {
                $phoneNumbers = $this->getRecipientPhoneNumbers($recipient);

                foreach ($phoneNumbers as $phone) {
                    $message = $this->buildSmsMessage($reportId, $notificationType);
                    $result = $this->sendSms($phone, $message);
                    $smsResults[] = $result;
                }
            } catch (Exception $e) {
                $smsResults[] = [
                    'recipient' => $recipient,
                    'success'   => false,
                    'error'     => $e->getMessage(),
                ];
            }
        }

        return [
            'method'     => 'sms',
            'attempted'  => count($smsResults),
            'successful' => count(array_filter($smsResults, fn ($r) => $r['success'])),
            'failed'     => count(array_filter($smsResults, fn ($r) => ! $r['success'])),
            'results'    => $smsResults,
        ];
    }

    // Helper methods

    private function getRecipientEmails(string $recipient): array
    {
        // In a real system, would look up emails from user database
        return match ($recipient) {
            'portfolio_manager'    => ['pm@example.com'],
            'risk_manager'         => ['risk@example.com'],
            'senior_management'    => ['senior@example.com'],
            'compliance_officer'   => ['compliance@example.com'],
            'operations_team'      => ['ops@example.com'],
            'investment_committee' => ['committee@example.com'],
            default                => ["${recipient}@example.com"],
        };
    }

    private function sendReportEmail(string $email, string $reportId, array $report, string $type, string $recipient): array
    {
        // Simulate email sending
        return [
            'recipient' => $recipient,
            'email'     => $email,
            'success'   => true,
            'subject'   => $this->getEmailSubject($type, $reportId),
            'sent_at'   => now()->toISOString(),
        ];
    }

    private function getEmailSubject(string $type, string $reportId): string
    {
        return match ($type) {
            'failure' => 'Portfolio Rebalancing Failed - Attention Required',
            'report'  => 'Portfolio Performance Report Available',
            default   => 'Portfolio Notification',
        };
    }

    private function createArchivePath(string $portfolioId, string $reportId): string
    {
        $year = date('Y');
        $month = date('m');

        return storage_path("app/archives/reports/{$year}/{$month}/{$portfolioId}_{$reportId}.pdf");
    }

    private function getWebhookUrls(array $recipients): array
    {
        // In a real system, would get webhook URLs from configuration
        return [
            'https://api.example.com/webhooks/reports' => ['method' => 'POST', 'auth' => 'bearer_token'],
        ];
    }

    private function sendWebhook(string $url, array $payload, array $config): array
    {
        // Simulate webhook sending
        return [
            'url'      => $url,
            'success'  => true,
            'response' => 'OK',
            'sent_at'  => now()->toISOString(),
        ];
    }

    private function buildChatMessage(string $portfolioId, string $reportId, array $report, string $type): string
    {
        return match ($type) {
            'failure' => "🚨 Portfolio rebalancing failed for portfolio {$portfolioId}. Report ID: {$reportId}",
            'report'  => "📊 New performance report available for portfolio {$portfolioId}. Download: " . ($report['download_url'] ?? 'N/A'),
            default   => "Portfolio notification for {$portfolioId}",
        };
    }

    private function getRecipientPhoneNumbers(string $recipient): array
    {
        // In a real system, would look up phone numbers from user database
        return ['+1234567890']; // Placeholder
    }

    private function buildSmsMessage(string $reportId, string $type): string
    {
        return match ($type) {
            'failure' => "ALERT: Portfolio rebalancing failed. Report ID: {$reportId}. Check dashboard immediately.",
            default   => "Portfolio notification: {$reportId}",
        };
    }

    private function sendSms(string $phone, string $message): array
    {
        // Simulate SMS sending
        return [
            'phone'   => $phone,
            'success' => true,
            'sent_at' => now()->toISOString(),
        ];
    }

    private function calculateDistributionSuccess(array $distributionResults): array
    {
        $successfulChannels = 0;
        $failedChannels = 0;
        $totalDeliveries = 0;
        $successfulDeliveries = [];
        $failedDeliveries = [];

        foreach ($distributionResults as $channel => $result) {
            if ($result['success'] ?? false) {
                $successfulChannels++;
            } else {
                $failedChannels++;
            }

            // Count individual deliveries for channels that track them
            if (isset($result['successful'])) {
                $totalDeliveries += $result['attempted'] ?? 0;
                $successfulDeliveries = array_merge($successfulDeliveries, array_filter($result['results'] ?? [], fn ($r) => $r['success']));
                $failedDeliveries = array_merge($failedDeliveries, array_filter($result['results'] ?? [], fn ($r) => ! $r['success']));
            }
        }

        $totalChannels = $successfulChannels + $failedChannels;
        $successRate = $totalChannels > 0 ? ($successfulChannels / $totalChannels) * 100 : 0;

        return [
            'overall_success'       => $failedChannels === 0,
            'successful_channels'   => $successfulChannels,
            'failed_channels'       => $failedChannels,
            'success_rate'          => $successRate,
            'successful_deliveries' => $successfulDeliveries,
            'failed_deliveries'     => $failedDeliveries,
        ];
    }
}
