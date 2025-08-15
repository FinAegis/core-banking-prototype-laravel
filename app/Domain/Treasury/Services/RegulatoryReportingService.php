<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Services;

use App\Domain\Treasury\Aggregates\TreasuryAggregate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegulatoryReportingService
{
    private array $reportTemplates = [
        'BASEL_III' => [
            'sections'  => ['capital_adequacy', 'liquidity_coverage', 'leverage_ratio'],
            'frequency' => 'quarterly',
            'format'    => 'xml',
        ],
        'FORM_10Q' => [
            'sections'  => ['financial_statements', 'treasury_positions', 'risk_disclosures'],
            'frequency' => 'quarterly',
            'format'    => 'pdf',
        ],
        'CALL_REPORT' => [
            'sections'  => ['balance_sheet', 'income_statement', 'asset_quality'],
            'frequency' => 'quarterly',
            'format'    => 'csv',
        ],
        'LIQUIDITY_COVERAGE_RATIO' => [
            'sections'  => ['hqla', 'net_cash_outflows', 'lcr_calculation'],
            'frequency' => 'daily',
            'format'    => 'json',
        ],
        'STRESS_TEST' => [
            'sections'  => ['baseline_scenario', 'adverse_scenario', 'severely_adverse_scenario'],
            'frequency' => 'annual',
            'format'    => 'pdf',
        ],
    ];

    public function generateReport(
        string $accountId,
        string $reportType,
        string $period,
        array $options = []
    ): array {
        // Validate report type
        if (! isset($this->reportTemplates[$reportType])) {
            throw new \InvalidArgumentException("Invalid report type: {$reportType}");
        }

        $template = $this->reportTemplates[$reportType];

        // Collect data for report
        $reportData = $this->collectReportData($accountId, $reportType, $period);

        // Validate data completeness
        $validation = $this->validateReportData($reportData, $template);

        if (! $validation['is_complete']) {
            throw new \RuntimeException('Report data incomplete: ' . implode(', ', $validation['missing']));
        }

        // Format report according to template
        $formattedReport = $this->formatReport($reportData, $template, $options);

        // Store report
        $reportId = $this->storeReport($accountId, $reportType, $formattedReport);

        // Update Treasury Aggregate
        $this->updateTreasuryAggregate($accountId, $reportId, $reportType, $period, $reportData);

        return [
            'report_id'    => $reportId,
            'report_type'  => $reportType,
            'period'       => $period,
            'status'       => 'generated',
            'format'       => $template['format'],
            'sections'     => array_keys($formattedReport),
            'generated_at' => now()->toIso8601String(),
            'file_path'    => $this->getReportFilePath($reportId, $template['format']),
        ];
    }

    private function collectReportData(string $accountId, string $reportType, string $period): array
    {
        $data = [];

        // Get Treasury Aggregate data
        $aggregate = TreasuryAggregate::retrieve($accountId);

        $data['account_info'] = [
            'account_id'   => $accountId,
            'balance'      => $aggregate->getBalance(),
            'risk_profile' => $aggregate->getRiskProfile()?->getLevel(),
            'allocations'  => $aggregate->getAllocations(),
        ];

        // Collect section-specific data
        $sections = $this->reportTemplates[$reportType]['sections'];

        foreach ($sections as $section) {
            $data[$section] = $this->collectSectionData($section, $accountId, $period);
        }

        return $data;
    }

    private function collectSectionData(string $section, string $accountId, string $period): array
    {
        return match ($section) {
            'capital_adequacy'          => $this->calculateCapitalAdequacy($accountId),
            'liquidity_coverage'        => $this->calculateLiquidityCoverage($accountId),
            'leverage_ratio'            => $this->calculateLeverageRatio($accountId),
            'financial_statements'      => $this->generateFinancialStatements($accountId, $period),
            'treasury_positions'        => $this->getTreasuryPositions($accountId),
            'risk_disclosures'          => $this->generateRiskDisclosures($accountId),
            'balance_sheet'             => $this->generateBalanceSheet($accountId, $period),
            'income_statement'          => $this->generateIncomeStatement($accountId, $period),
            'asset_quality'             => $this->assessAssetQuality($accountId),
            'hqla'                      => $this->calculateHQLA($accountId),
            'net_cash_outflows'         => $this->calculateNetCashOutflows($accountId),
            'lcr_calculation'           => $this->calculateLCR($accountId),
            'baseline_scenario'         => $this->runStressTest($accountId, 'baseline'),
            'adverse_scenario'          => $this->runStressTest($accountId, 'adverse'),
            'severely_adverse_scenario' => $this->runStressTest($accountId, 'severely_adverse'),
            default                     => [],
        };
    }

    private function calculateCapitalAdequacy(string $accountId): array
    {
        // Basel III Capital Adequacy Ratio calculation
        return [
            'tier1_capital'          => 5000000,
            'tier2_capital'          => 2000000,
            'risk_weighted_assets'   => 50000000,
            'capital_adequacy_ratio' => 14.0, // (T1 + T2) / RWA * 100
            'minimum_requirement'    => 8.0,
            'buffer'                 => 6.0,
        ];
    }

    private function calculateLiquidityCoverage(string $accountId): array
    {
        return [
            'high_quality_liquid_assets' => 10000000,
            'total_net_cash_outflows'    => 8000000,
            'lcr_ratio'                  => 125.0, // HQLA / Net outflows * 100
            'minimum_requirement'        => 100.0,
            'excess_coverage'            => 25.0,
        ];
    }

    private function calculateLeverageRatio(string $accountId): array
    {
        return [
            'tier1_capital'       => 5000000,
            'total_exposure'      => 100000000,
            'leverage_ratio'      => 5.0, // T1 / Exposure * 100
            'minimum_requirement' => 3.0,
            'buffer'              => 2.0,
        ];
    }

    private function generateFinancialStatements(string $accountId, string $period): array
    {
        return [
            'assets' => [
                'cash_and_equivalents' => 10000000,
                'investments'          => 40000000,
                'loans'                => 30000000,
                'total'                => 80000000,
            ],
            'liabilities' => [
                'deposits'   => 60000000,
                'borrowings' => 10000000,
                'total'      => 70000000,
            ],
            'equity' => 10000000,
            'period' => $period,
        ];
    }

    private function getTreasuryPositions(string $accountId): array
    {
        return [
            'money_market'    => 5000000,
            'treasury_bonds'  => 15000000,
            'corporate_bonds' => 10000000,
            'equities'        => 5000000,
            'derivatives'     => 2000000,
            'total'           => 37000000,
        ];
    }

    private function generateRiskDisclosures(string $accountId): array
    {
        return [
            'market_risk' => [
                'var_95'      => 250000,
                'var_99'      => 500000,
                'stress_loss' => 1000000,
            ],
            'credit_risk' => [
                'expected_loss'   => 100000,
                'unexpected_loss' => 300000,
                'provisions'      => 150000,
            ],
            'operational_risk' => [
                'capital_charge' => 200000,
                'loss_events'    => 5,
                'total_losses'   => 50000,
            ],
        ];
    }

    private function generateBalanceSheet(string $accountId, string $period): array
    {
        return $this->generateFinancialStatements($accountId, $period);
    }

    private function generateIncomeStatement(string $accountId, string $period): array
    {
        return [
            'interest_income'     => 2000000,
            'interest_expense'    => 800000,
            'net_interest_income' => 1200000,
            'non_interest_income' => 500000,
            'operating_expenses'  => 900000,
            'provisions'          => 100000,
            'net_income'          => 700000,
            'period'              => $period,
        ];
    }

    private function assessAssetQuality(string $accountId): array
    {
        return [
            'performing_assets'     => 75000000,
            'non_performing_assets' => 5000000,
            'npa_ratio'             => 6.25,
            'provision_coverage'    => 80.0,
            'net_npa_ratio'         => 1.25,
        ];
    }

    private function calculateHQLA(string $accountId): array
    {
        return [
            'level_1_assets'  => 8000000,
            'level_2a_assets' => 1500000,
            'level_2b_assets' => 500000,
            'total_hqla'      => 10000000,
        ];
    }

    private function calculateNetCashOutflows(string $accountId): array
    {
        return [
            'retail_deposits_outflow'   => 3000000,
            'wholesale_funding_outflow' => 4000000,
            'secured_funding_outflow'   => 1000000,
            'total_outflows'            => 8000000,
            'total_inflows'             => 2000000,
            'net_cash_outflows'         => 6000000,
        ];
    }

    private function calculateLCR(string $accountId): array
    {
        $hqla = $this->calculateHQLA($accountId);
        $outflows = $this->calculateNetCashOutflows($accountId);

        return [
            'hqla'                => $hqla['total_hqla'],
            'net_cash_outflows'   => $outflows['net_cash_outflows'],
            'lcr_ratio'           => ($hqla['total_hqla'] / $outflows['net_cash_outflows']) * 100,
            'minimum_requirement' => 100.0,
        ];
    }

    private function runStressTest(string $accountId, string $scenario): array
    {
        $stressFactors = match ($scenario) {
            'baseline'         => 1.0,
            'adverse'          => 1.5,
            'severely_adverse' => 2.5,
            default            => 1.0,
        };

        return [
            'scenario'         => $scenario,
            'projected_losses' => 1000000 * $stressFactors,
            'capital_impact'   => 500000 * $stressFactors,
            'liquidity_impact' => 2000000 * $stressFactors,
            'survival_horizon' => max(12 / $stressFactors, 3),
        ];
    }

    private function validateReportData(array $data, array $template): array
    {
        $requiredSections = $template['sections'];
        $presentSections = array_keys($data);
        $missingSections = array_diff($requiredSections, $presentSections);

        return [
            'is_complete' => empty($missingSections),
            'missing'     => $missingSections,
        ];
    }

    private function formatReport(array $data, array $template, array $options): array
    {
        $formatted = [];

        foreach ($template['sections'] as $section) {
            $formatted[$section] = $this->formatSection($data[$section], $template['format']);
        }

        // Add metadata
        $formatted['metadata'] = [
            'generated_at' => now()->toIso8601String(),
            'format'       => $template['format'],
            'frequency'    => $template['frequency'],
            'options'      => $options,
        ];

        return $formatted;
    }

    private function formatSection(array $sectionData, string $format): array
    {
        // In production, would apply format-specific transformations
        // For demo, return structured data
        return $sectionData;
    }

    private function storeReport(string $accountId, string $reportType, array $report): string
    {
        $reportId = Str::uuid()->toString();
        $format = $this->reportTemplates[$reportType]['format'];
        $filePath = $this->getReportFilePath($reportId, $format);

        // Store report based on format
        $content = match ($format) {
            'json'  => json_encode($report, JSON_PRETTY_PRINT),
            'csv'   => $this->convertToCSV($report),
            'xml'   => $this->convertToXML($report),
            default => json_encode($report),
        };

        if ($content !== false) {
            Storage::put($filePath, $content);
        }

        return $reportId;
    }

    private function getReportFilePath(string $reportId, string $format): string
    {
        return "treasury/reports/{$reportId}.{$format}";
    }

    private function convertToCSV(array $data): string
    {
        // Simple CSV conversion for demo
        $csv = '';
        foreach ($data as $section => $content) {
            if (is_array($content)) {
                $csv .= "\n[{$section}]\n";
                foreach ($content as $key => $value) {
                    $csv .= "{$key}," . (is_array($value) ? json_encode($value) : $value) . "\n";
                }
            }
        }

        return $csv;
    }

    private function convertToXML(array $data): string
    {
        // Simple XML conversion for demo
        $xml = '<?xml version="1.0" encoding="UTF-8"?><report>';
        foreach ($data as $section => $content) {
            $xml .= "<{$section}>";
            if (is_array($content)) {
                foreach ($content as $key => $value) {
                    $xml .= "<{$key}>" . (is_array($value) ? json_encode($value) : $value) . "</{$key}>";
                }
            } else {
                $xml .= $content;
            }
            $xml .= "</{$section}>";
        }
        $xml .= '</report>';

        return $xml;
    }

    private function updateTreasuryAggregate(
        string $accountId,
        string $reportId,
        string $reportType,
        string $period,
        array $data
    ): void {
        $aggregate = TreasuryAggregate::retrieve($accountId);

        $aggregate->generateRegulatoryReport(
            $reportId,
            $reportType,
            $period,
            $data,
            'regulatory_reporting_service'
        );

        $aggregate->persist();
    }
}
