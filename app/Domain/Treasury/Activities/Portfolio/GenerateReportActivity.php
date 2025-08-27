<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities\Portfolio;

use App\Domain\Treasury\Events\Portfolio\ReportGenerated;
use Exception;
use InvalidArgumentException;
use Log;
use RuntimeException;
use Workflow\Activity;

class GenerateReportActivity extends Activity
{
    public function execute(array $input): array
    {
        $portfolioId = $input['portfolio_id'];
        $reportId = $input['report_id'];
        $reportType = $input['report_type'];
        $period = $input['period'];
        $performanceData = $input['performance_data'];
        $formatOptions = $input['format_options'] ?? [];

        try {
            Log::info('Generating performance report', [
                'portfolio_id' => $portfolioId,
                'report_id'    => $reportId,
                'report_type'  => $reportType,
                'format'       => $formatOptions['format'] ?? 'pdf',
            ]);

            // Generate report based on format
            $format = $formatOptions['format'] ?? 'pdf';
            $generatedReport = match ($format) {
                'pdf'   => $this->generatePdfReport($reportId, $reportType, $performanceData, $formatOptions),
                'html'  => $this->generateHtmlReport($reportId, $reportType, $performanceData, $formatOptions),
                'excel' => $this->generateExcelReport($reportId, $reportType, $performanceData, $formatOptions),
                'json'  => $this->generateJsonReport($reportId, $reportType, $performanceData, $formatOptions),
                default => throw new InvalidArgumentException("Unsupported report format: {$format}"),
            };

            // Dispatch event for successful report generation
            event(new ReportGenerated(
                $portfolioId,
                $reportId,
                $reportType,
                $period,
                $generatedReport['file_path'],
                $format,
                [
                    'generated_at' => $generatedReport['generated_at'],
                    'file_size'    => $generatedReport['file_size'],
                    'page_count'   => $generatedReport['page_count'] ?? 1,
                ]
            ));

            Log::info('Performance report generated successfully', [
                'portfolio_id' => $portfolioId,
                'report_id'    => $reportId,
                'file_path'    => $generatedReport['file_path'],
                'file_size'    => $generatedReport['file_size'],
                'format'       => $format,
            ]);

            return $generatedReport;
        } catch (Exception $e) {
            Log::error('Report generation failed', [
                'portfolio_id' => $portfolioId,
                'report_id'    => $reportId,
                'error'        => $e->getMessage(),
            ]);

            throw new RuntimeException(
                "Failed to generate report for portfolio {$portfolioId}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Generate PDF report using HTML to PDF conversion.
     */
    private function generatePdfReport(string $reportId, string $reportType, array $data, array $options): array
    {
        $htmlContent = $this->generateReportHtml($reportId, $reportType, $data, $options);

        // In a real implementation, this would use a PDF library like TCPDF, mPDF, or wkhtmltopdf
        $fileName = "portfolio_report_{$reportId}_{$reportType}_" . date('Y-m-d_H-i-s') . '.pdf';
        $filePath = storage_path("app/reports/pdf/{$fileName}");

        // Ensure directory exists
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Simulate PDF generation (in reality, would convert HTML to PDF)
        $pdfContent = $this->simulatePdfGeneration($htmlContent, $options);
        file_put_contents($filePath, $pdfContent);

        return [
            'format'       => 'pdf',
            'file_path'    => $filePath,
            'file_name'    => $fileName,
            'file_size'    => filesize($filePath),
            'page_count'   => $this->estimatePageCount($htmlContent),
            'generated_at' => now()->toISOString(),
            'content_type' => 'application/pdf',
            'download_url' => url("/reports/download/{$reportId}"),
        ];
    }

    /**
     * Generate HTML report.
     */
    private function generateHtmlReport(string $reportId, string $reportType, array $data, array $options): array
    {
        $htmlContent = $this->generateReportHtml($reportId, $reportType, $data, $options);

        $fileName = "portfolio_report_{$reportId}_{$reportType}_" . date('Y-m-d_H-i-s') . '.html';
        $filePath = storage_path("app/reports/html/{$fileName}");

        // Ensure directory exists
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($filePath, $htmlContent);

        return [
            'format'       => 'html',
            'file_path'    => $filePath,
            'file_name'    => $fileName,
            'file_size'    => filesize($filePath),
            'generated_at' => now()->toISOString(),
            'content_type' => 'text/html',
            'view_url'     => url("/reports/view/{$reportId}"),
        ];
    }

    /**
     * Generate Excel report.
     */
    private function generateExcelReport(string $reportId, string $reportType, array $data, array $options): array
    {
        // In a real implementation, this would use PhpSpreadsheet or similar
        $fileName = "portfolio_report_{$reportId}_{$reportType}_" . date('Y-m-d_H-i-s') . '.xlsx';
        $filePath = storage_path("app/reports/excel/{$fileName}");

        // Ensure directory exists
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $excelContent = $this->generateExcelContent($data, $options);
        file_put_contents($filePath, $excelContent);

        return [
            'format'       => 'excel',
            'file_path'    => $filePath,
            'file_name'    => $fileName,
            'file_size'    => filesize($filePath),
            'generated_at' => now()->toISOString(),
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'download_url' => url("/reports/download/{$reportId}"),
            'sheets'       => ['Summary', 'Performance', 'Holdings', 'Benchmarks'],
        ];
    }

    /**
     * Generate JSON report.
     */
    private function generateJsonReport(string $reportId, string $reportType, array $data, array $options): array
    {
        $fileName = "portfolio_report_{$reportId}_{$reportType}_" . date('Y-m-d_H-i-s') . '.json';
        $filePath = storage_path("app/reports/json/{$fileName}");

        // Ensure directory exists
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($filePath, $jsonContent);

        return [
            'format'       => 'json',
            'file_path'    => $filePath,
            'file_name'    => $fileName,
            'file_size'    => filesize($filePath),
            'generated_at' => now()->toISOString(),
            'content_type' => 'application/json',
            'api_url'      => url("/api/reports/{$reportId}"),
        ];
    }

    /**
     * Generate HTML content for the report.
     */
    private function generateReportHtml(string $reportId, string $reportType, array $data, array $options): string
    {
        $template = $options['template'] ?? 'standard';
        $includeBranding = $options['branding'] ?? true;
        $includeCharts = $options['include_charts'] ?? true;

        // Build HTML content based on template and data
        $html = $this->getHtmlTemplate($template, $includeBranding);

        // Replace placeholders with actual data
        $replacements = [
            '{{REPORT_TITLE}}'      => $this->getReportTitle($reportType, $data),
            '{{PORTFOLIO_NAME}}'    => $data['portfolio_info']['name'] ?? 'Unknown Portfolio',
            '{{REPORT_DATE}}'       => now()->format('F j, Y'),
            '{{PERIOD}}'            => strtoupper($data['period']),
            '{{TOTAL_RETURN}}'      => $this->formatPercentage($data['returns']['total_return']),
            '{{ANNUALIZED_RETURN}}' => $this->formatPercentage($data['returns']['annualized_return']),
            '{{SHARPE_RATIO}}'      => number_format($data['risk_metrics']['sharpe_ratio'], 2),
            '{{VOLATILITY}}'        => $this->formatPercentage($data['risk_metrics']['volatility']),
            '{{PORTFOLIO_VALUE}}'   => $this->formatCurrency($data['portfolio_info']['total_value']),
            '{{PERFORMANCE_GRADE}}' => $data['summary']['performance_grade'],
            '{{SUMMARY_CONTENT}}'   => $this->generateSummaryContent($data),
            '{{HOLDINGS_TABLE}}'    => $this->generateHoldingsTable($data['holdings'] ?? []),
            '{{BENCHMARK_TABLE}}'   => $this->generateBenchmarkTable($data['benchmark_comparison'] ?? []),
            '{{CHARTS}}'            => $includeCharts ? $this->generateCharts($data) : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    /**
     * Get HTML template based on type.
     */
    private function getHtmlTemplate(string $template, bool $includeBranding): string
    {
        $baseTemplate = '<!DOCTYPE html>
<html>
<head>
    <title>{{REPORT_TITLE}}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .portfolio-name { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .report-date { color: #7f8c8d; }
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
        .metric-card { border: 1px solid #ddd; padding: 15px; border-radius: 5px; text-align: center; }
        .metric-value { font-size: 20px; font-weight: bold; color: #27ae60; }
        .metric-label { font-size: 12px; color: #7f8c8d; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .grade-a { color: #27ae60; } .grade-b { color: #f39c12; } .grade-c { color: #e67e22; } .grade-d { color: #e74c3c; }
        .highlight { background-color: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 10px 0; }
        .concern { background-color: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="header">
        <div class="portfolio-name">{{PORTFOLIO_NAME}}</div>
        <div class="report-date">{{REPORT_TITLE}} - {{REPORT_DATE}}</div>
    </div>
    
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-value">{{TOTAL_RETURN}}</div>
            <div class="metric-label">Total Return ({{PERIOD}})</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">{{ANNUALIZED_RETURN}}</div>
            <div class="metric-label">Annualized Return</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">{{SHARPE_RATIO}}</div>
            <div class="metric-label">Sharpe Ratio</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">{{VOLATILITY}}</div>
            <div class="metric-label">Volatility</div>
        </div>
        <div class="metric-card">
            <div class="metric-value grade-{{PERFORMANCE_GRADE}}">{{PERFORMANCE_GRADE}}</div>
            <div class="metric-label">Performance Grade</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">{{PORTFOLIO_VALUE}}</div>
            <div class="metric-label">Total Value</div>
        </div>
    </div>
    
    {{SUMMARY_CONTENT}}
    
    {{CHARTS}}
    
    <h2>Portfolio Holdings</h2>
    {{HOLDINGS_TABLE}}
    
    <h2>Benchmark Comparison</h2>
    {{BENCHMARK_TABLE}}
    
    ' . ($includeBranding ? '<div style="margin-top: 50px; text-align: center; color: #7f8c8d; font-size: 12px;">Generated by FinAegis Portfolio Management System</div>' : '') . '
</body>
</html>';

        return $baseTemplate;
    }

    /**
     * Generate summary content section.
     */
    private function generateSummaryContent(array $data): string
    {
        $content = '<h2>Executive Summary</h2>';

        // Key highlights
        if (! empty($data['summary']['key_highlights'])) {
            $content .= '<h3>Key Highlights</h3>';
            foreach ($data['summary']['key_highlights'] as $highlight) {
                $content .= "<div class=\"highlight\">{$highlight}</div>";
            }
        }

        // Areas of concern
        if (! empty($data['summary']['areas_of_concern'])) {
            $content .= '<h3>Areas of Concern</h3>';
            foreach ($data['summary']['areas_of_concern'] as $concern) {
                $content .= "<div class=\"concern\">{$concern}</div>";
            }
        }

        // Recommendations
        if (! empty($data['summary']['recommendations'])) {
            $content .= '<h3>Recommendations</h3><ul>';
            foreach ($data['summary']['recommendations'] as $recommendation) {
                $content .= "<li>{$recommendation}</li>";
            }
            $content .= '</ul>';
        }

        return $content;
    }

    /**
     * Generate holdings table.
     */
    private function generateHoldingsTable(array $holdings): string
    {
        if (empty($holdings['holdings'])) {
            return '<p>No holdings data available.</p>';
        }

        $table = '<table><thead><tr><th>Asset Class</th><th>Target Weight</th><th>Current Weight</th><th>Current Value</th><th>Drift</th><th>Grade</th></tr></thead><tbody>';

        foreach ($holdings['holdings'] as $holding) {
            $table .= '<tr>';
            $table .= '<td>' . htmlspecialchars($holding['asset_class']) . '</td>';
            $table .= '<td>' . $this->formatPercentage($holding['target_weight'] / 100) . '</td>';
            $table .= '<td>' . $this->formatPercentage($holding['current_weight'] / 100) . '</td>';
            $table .= '<td>' . $this->formatCurrency($holding['current_value']) . '</td>';
            $table .= '<td>' . $this->formatPercentage($holding['drift'] / 100) . '</td>';
            $table .= '<td>' . htmlspecialchars($holding['allocation_grade']) . '</td>';
            $table .= '</tr>';
        }

        $table .= '</tbody></table>';

        return $table;
    }

    /**
     * Generate benchmark comparison table.
     */
    private function generateBenchmarkTable(array $benchmarkComparison): string
    {
        if (empty($benchmarkComparison['benchmarks'])) {
            return '<p>No benchmark comparison data available.</p>';
        }

        $table = '<table><thead><tr><th>Benchmark</th><th>Benchmark Return</th><th>Portfolio Return</th><th>Excess Return</th><th>Relative Performance</th></tr></thead><tbody>';

        foreach ($benchmarkComparison['benchmarks'] as $benchmark => $data) {
            $table .= '<tr>';
            $table .= '<td>' . htmlspecialchars($data['name']) . '</td>';
            $table .= '<td>' . $this->formatPercentage($data['benchmark_return']) . '</td>';
            $table .= '<td>' . $this->formatPercentage($data['portfolio_return']) . '</td>';
            $table .= '<td class="' . ($data['excess_return'] >= 0 ? 'grade-a' : 'grade-d') . '">' . $this->formatPercentage($data['excess_return']) . '</td>';
            $table .= '<td>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $data['relative_performance']))) . '</td>';
            $table .= '</tr>';
        }

        $table .= '</tbody></table>';

        return $table;
    }

    /**
     * Generate charts placeholder (in a real system would generate actual charts).
     */
    private function generateCharts(array $data): string
    {
        return '<h2>Performance Charts</h2>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; margin: 20px 0; border: 1px dashed #ccc;">
            <p>Performance charts would be generated here</p>
            <p>Charts: Performance over time, Asset allocation pie chart, Risk/Return scatter plot</p>
        </div>';
    }

    // Helper methods

    private function getReportTitle(string $reportType, array $data): string
    {
        return match ($reportType) {
            'daily'     => 'Daily Performance Report',
            'weekly'    => 'Weekly Performance Report',
            'monthly'   => 'Monthly Performance Report',
            'quarterly' => 'Quarterly Performance Report',
            'annual'    => 'Annual Performance Report',
            default     => ucfirst($reportType) . ' Performance Report',
        };
    }

    private function formatPercentage(float $value): string
    {
        return number_format($value * 100, 2) . '%';
    }

    private function formatCurrency(float $value): string
    {
        return '$' . number_format($value, 2);
    }

    private function estimatePageCount(string $content): int
    {
        // Rough estimation: ~500 words per page
        $wordCount = str_word_count(strip_tags($content));

        return (int) max(1, ceil($wordCount / 500));
    }

    private function simulatePdfGeneration(string $htmlContent, array $options): string
    {
        // In a real implementation, this would use a PDF library
        return 'PDF content for: ' . substr(strip_tags($htmlContent), 0, 100) . '...';
    }

    private function generateExcelContent(array $data, array $options): string
    {
        // In a real implementation, this would create actual Excel content
        return 'Excel content with performance data';
    }
}
