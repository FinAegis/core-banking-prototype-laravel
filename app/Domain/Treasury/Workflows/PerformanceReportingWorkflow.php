<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Workflows;

use App\Domain\Treasury\Activities\Portfolio\CalculatePerformanceActivity;
use App\Domain\Treasury\Activities\Portfolio\DistributeReportActivity;
use App\Domain\Treasury\Activities\Portfolio\GenerateReportActivity;
use Exception;
use Illuminate\Support\Str;
use Log;
use RuntimeException;
use Workflow\Activity;
use Workflow\Workflow;

class PerformanceReportingWorkflow extends Workflow
{
    private string $reportId;

    private string $portfolioId;

    private string $reportType;

    private string $period;

    private array $performanceData = [];

    private array $generatedReport = [];

    public function execute(
        string $portfolioId,
        string $reportType = 'monthly',
        string $period = '30d',
        array $recipients = [],
        array $options = []
    ) {
        $this->portfolioId = $portfolioId;
        $this->reportType = $reportType;
        $this->period = $period;
        $this->reportId = Str::uuid()->toString();

        try {
            // Step 1: Calculate performance metrics for the specified period
            $this->performanceData = yield Activity::make(CalculatePerformanceActivity::class, [
                'portfolio_id' => $this->portfolioId,
                'report_id'    => $this->reportId,
                'period'       => $this->period,
                'report_type'  => $this->reportType,
                'options'      => array_merge([
                    'include_benchmarks'   => true,
                    'include_attribution'  => $this->reportType === 'quarterly' || $this->reportType === 'annual',
                    'include_risk_metrics' => true,
                    'include_holdings'     => $this->reportType !== 'daily',
                    'detailed_breakdown'   => in_array($this->reportType, ['quarterly', 'annual']),
                ], $options),
            ]);

            // Step 2: Generate the performance report in the requested format
            $this->generatedReport = yield Activity::make(GenerateReportActivity::class, [
                'portfolio_id'     => $this->portfolioId,
                'report_id'        => $this->reportId,
                'report_type'      => $this->reportType,
                'period'           => $this->period,
                'performance_data' => $this->performanceData,
                'format_options'   => $options['format'] ?? [
                    'include_charts' => true,
                    'format'         => 'pdf', // pdf, html, excel
                    'template'       => 'standard',
                    'branding'       => true,
                ],
            ]);

            // Step 3: Distribute the report to stakeholders
            $distribution = yield Activity::make(DistributeReportActivity::class, [
                'portfolio_id'     => $this->portfolioId,
                'report_id'        => $this->reportId,
                'generated_report' => $this->generatedReport,
                'recipients'       => $this->getRecipients($recipients),
                'distribution'     => array_merge([
                    'email'       => true,
                    'dashboard'   => true,
                    'archive'     => true,
                    'api_webhook' => false,
                ], $options['distribution'] ?? []),
            ]);

            return [
                'success'          => true,
                'report_id'        => $this->reportId,
                'portfolio_id'     => $this->portfolioId,
                'report_type'      => $this->reportType,
                'period'           => $this->period,
                'generated_at'     => now()->toISOString(),
                'performance_data' => [
                    'total_return'     => $this->performanceData['returns']['total_return'] ?? 0,
                    'benchmark_return' => $this->performanceData['benchmark_comparison']['primary']['benchmark_return'] ?? 0,
                    'sharpe_ratio'     => $this->performanceData['risk_metrics']['sharpe_ratio'] ?? 0,
                    'volatility'       => $this->performanceData['risk_metrics']['volatility'] ?? 0,
                ],
                'report_details' => [
                    'file_path'  => $this->generatedReport['file_path'] ?? null,
                    'file_size'  => $this->generatedReport['file_size'] ?? 0,
                    'format'     => $this->generatedReport['format'] ?? 'pdf',
                    'page_count' => $this->generatedReport['page_count'] ?? 1,
                ],
                'distribution_results' => $distribution,
                'recipients_notified'  => count($distribution['successful_deliveries'] ?? []),
            ];
        } catch (Exception $e) {
            // Compensation: Clean up any generated files or partial reports
            yield from $this->compensate();

            throw new RuntimeException(
                "Performance reporting workflow failed for portfolio {$this->portfolioId}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    public function compensate()
    {
        // Clean up generated report files if they exist
        if (! empty($this->generatedReport['file_path'])) {
            try {
                if (file_exists($this->generatedReport['file_path'])) {
                    unlink($this->generatedReport['file_path']);
                }
            } catch (Exception $e) {
                Log::warning('Failed to clean up report file during compensation', [
                    'report_id' => $this->reportId,
                    'file_path' => $this->generatedReport['file_path'],
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // Log the failure for audit purposes
        Log::error('Performance reporting workflow failed and was compensated', [
            'portfolio_id' => $this->portfolioId,
            'report_id'    => $this->reportId,
            'report_type'  => $this->reportType,
            'period'       => $this->period,
        ]);

        // Optionally notify stakeholders of the failure
        if (! empty($this->performanceData)) {
            yield Activity::make(DistributeReportActivity::class, [
                'portfolio_id'     => $this->portfolioId,
                'report_id'        => $this->reportId,
                'generated_report' => [
                    'status' => 'failed',
                    'error'  => 'Report generation failed',
                ],
                'recipients'   => $this->getRecipients([]),
                'distribution' => [
                    'email'       => true,
                    'dashboard'   => false,
                    'archive'     => false,
                    'api_webhook' => false,
                ],
                'notification_type' => 'failure',
            ]);
        }
    }

    /**
     * Get the list of recipients for the report based on report type and portfolio.
     */
    private function getRecipients(array $overrideRecipients): array
    {
        if (! empty($overrideRecipients)) {
            return $overrideRecipients;
        }

        // Default recipients based on report type
        $defaultRecipients = match ($this->reportType) {
            'daily' => [
                'portfolio_managers',
            ],
            'weekly' => [
                'portfolio_managers',
                'risk_managers',
            ],
            'monthly' => [
                'portfolio_managers',
                'risk_managers',
                'investment_committee',
                'compliance',
            ],
            'quarterly' => [
                'portfolio_managers',
                'risk_managers',
                'investment_committee',
                'compliance',
                'board_members',
                'external_auditors',
            ],
            'annual' => [
                'all_stakeholders',
                'regulatory_authorities',
                'external_auditors',
                'board_members',
            ],
            default => [
                'portfolio_managers',
                'risk_managers',
            ],
        };

        return $defaultRecipients;
    }
}
