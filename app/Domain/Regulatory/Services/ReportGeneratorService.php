<?php

namespace App\Domain\Regulatory\Services;

use App\Domain\Regulatory\Models\RegulatoryReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SimpleXMLElement;

class ReportGeneratorService
{
    /**
     * Generate report in specified format.
     */
    public function generateReport(RegulatoryReport $report, ?string $format = null): string
    {
        $format = $format ?? $report->file_format;

        return match ($format) {
            RegulatoryReport::FORMAT_JSON => $this->generateJsonReport($report),
            RegulatoryReport::FORMAT_XML  => $this->generateXmlReport($report),
            RegulatoryReport::FORMAT_CSV  => $this->generateCsvReport($report),
            RegulatoryReport::FORMAT_PDF  => $this->generatePdfReport($report),
            RegulatoryReport::FORMAT_XLSX => $this->generateExcelReport($report),
            default                       => throw new InvalidArgumentException("Unsupported format: {$format}"),
        };
    }

    /**
     * Generate JSON report.
     */
    protected function generateJsonReport(RegulatoryReport $report): string
    {
        $data = $this->prepareReportData($report);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $filename = $this->getFilename($report, 'json');
        Storage::put($filename, $json);

        $this->updateReportFile($report, $filename, strlen($json));

        return $filename;
    }

    /**
     * Generate XML report.
     */
    protected function generateXmlReport(RegulatoryReport $report): string
    {
        $data = $this->prepareReportData($report);

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><report></report>');
        $this->arrayToXml($data, $xml);

        $xmlString = $xml->asXML();

        $filename = $this->getFilename($report, 'xml');
        Storage::put($filename, $xmlString);

        $this->updateReportFile($report, $filename, strlen($xmlString));

        return $filename;
    }

    /**
     * Generate CSV report.
     */
    protected function generateCsvReport(RegulatoryReport $report): string
    {
        $data = $this->prepareReportData($report);

        $filename = $this->getFilename($report, 'csv');
        $path = Storage::path($filename);

        $file = fopen($path, 'w');

        // Write headers
        $headers = $this->extractCsvHeaders($report);
        fputcsv($file, $headers);

        // Write data
        $rows = $this->extractCsvRows($report, $data);
        foreach ($rows as $row) {
            fputcsv($file, $row);
        }

        fclose($file);

        $this->updateReportFile($report, $filename, filesize($path));

        return $filename;
    }

    /**
     * Generate PDF report.
     */
    protected function generatePdfReport(RegulatoryReport $report): string
    {
        $data = $this->prepareReportData($report);

        // Load appropriate template based on report type
        $template = $this->getReportTemplate($report->report_type);

        $pdf = Pdf::loadView(
            $template,
            [
            'report'       => $report,
            'data'         => $data,
            'generated_at' => now(),
            ]
        );

        $pdf->setPaper('A4', 'portrait');

        $filename = $this->getFilename($report, 'pdf');
        $pdfContent = $pdf->output();

        Storage::put($filename, $pdfContent);

        $this->updateReportFile($report, $filename, strlen($pdfContent));

        return $filename;
    }

    /**
     * Generate Excel report.
     */
    protected function generateExcelReport(RegulatoryReport $report): string
    {
        $data = $this->prepareReportData($report);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add report header
        $this->addExcelHeader($sheet, $report);

        // Add data based on report type
        $this->addExcelData($sheet, $report, $data);

        // Auto-size columns
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        $filename = $this->getFilename($report, 'xlsx');
        $path = Storage::path($filename);

        $writer->save($path);

        $this->updateReportFile($report, $filename, filesize($path));

        return $filename;
    }

    /**
     * Prepare report data.
     */
    protected function prepareReportData(RegulatoryReport $report): array
    {
        // Load existing report data if available
        if ($report->file_path && Storage::exists($report->file_path)) {
            $content = Storage::get($report->file_path);
            if ($report->file_format === RegulatoryReport::FORMAT_JSON) {
                $existingData = json_decode($content, true);
            } else {
                $existingData = [];
            }
        } else {
            $existingData = [];
        }

        // Merge with report metadata
        return array_merge(
            $existingData,
            [
            'report_metadata' => [
                'report_id'        => $report->report_id,
                'report_type'      => $report->report_type,
                'jurisdiction'     => $report->jurisdiction,
                'reporting_period' => [
                    'start' => $report->reporting_period_start->toDateString(),
                    'end'   => $report->reporting_period_end->toDateString(),
                ],
                'generated_at'   => $report->generated_at->toIso8601String(),
                'report_version' => '1.0',
                'institution'    => [
                    'name'       => config('app.name'),
                    'identifier' => config('regulatory.institution_id'),
                ],
            ],
            'report_summary'           => $report->report_data ?? [],
            'compliance_certification' => [
                'certified_by'            => null,
                'certified_at'            => null,
                'certification_statement' => $this->getCertificationStatement($report),
            ],
            ]
        );
    }

    /**
     * Convert array to XML.
     */
    protected function arrayToXml(array $data, SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item_' . $key;
                }
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }

    /**
     * Extract CSV headers based on report type.
     */
    protected function extractCsvHeaders(RegulatoryReport $report): array
    {
        return match ($report->report_type) {
            RegulatoryReport::TYPE_CTR => [
                'Transaction ID', 'Date', 'Time', 'Type', 'Amount', 'Currency',
                'Account Number', 'Customer Name', 'Customer ID', 'Customer Type',
                'Address', 'City', 'State', 'Country', 'Postal Code',
            ],
            RegulatoryReport::TYPE_SAR => [
                'Activity ID', 'Date', 'Type', 'Risk Score', 'Customer Name',
                'Customer ID', 'Account Numbers', 'Total Amount', 'Currency',
                'Suspicious Activity Description', 'Risk Indicators',
            ],
            RegulatoryReport::TYPE_KYC => [
                'Customer ID', 'Name', 'Email', 'KYC Status', 'KYC Level',
                'Risk Rating', 'PEP Status', 'Country', 'Verification Date',
                'Expiry Date', 'Documents Verified',
            ],
            default => ['ID', 'Date', 'Type', 'Amount', 'Description'],
        };
    }

    /**
     * Extract CSV rows based on report type.
     */
    protected function extractCsvRows(RegulatoryReport $report, array $data): array
    {
        $rows = [];

        // Extract rows based on report type
        // This is a simplified version - implement based on actual data structure
        if (isset($data['transactions'])) {
            foreach ($data['transactions'] as $transaction) {
                $rows[] = $this->extractTransactionRow($transaction);
            }
        } elseif (isset($data['activities'])) {
            foreach ($data['activities'] as $activity) {
                $rows[] = $this->extractActivityRow($activity);
            }
        }

        return $rows;
    }

    /**
     * Extract transaction row for CSV.
     */
    protected function extractTransactionRow(array $transaction): array
    {
        return [
            $transaction['transaction_id'] ?? '',
            $transaction['date'] ?? '',
            $transaction['time'] ?? '',
            $transaction['type'] ?? '',
            $transaction['amount'] ?? '',
            $transaction['currency'] ?? '',
            $transaction['account_number'] ?? '',
            $transaction['customer_name'] ?? '',
            $transaction['customer_id'] ?? '',
            $transaction['customer_type'] ?? '',
            $transaction['address'] ?? '',
            $transaction['city'] ?? '',
            $transaction['state'] ?? '',
            $transaction['country'] ?? '',
            $transaction['postal_code'] ?? '',
        ];
    }

    /**
     * Extract activity row for CSV.
     */
    protected function extractActivityRow(array $activity): array
    {
        return [
            $activity['id'] ?? '',
            $activity['date'] ?? '',
            $activity['type'] ?? '',
            $activity['risk_score'] ?? '',
            $activity['customer_name'] ?? '',
            $activity['customer_id'] ?? '',
            implode(', ', $activity['account_numbers'] ?? []),
            $activity['total_amount'] ?? '',
            $activity['currency'] ?? '',
            $activity['description'] ?? '',
            implode(', ', $activity['risk_indicators'] ?? []),
        ];
    }

    /**
     * Get report template path.
     */
    protected function getReportTemplate(string $reportType): string
    {
        $templates = [
            RegulatoryReport::TYPE_CTR  => 'regulatory.reports.ctr',
            RegulatoryReport::TYPE_SAR  => 'regulatory.reports.sar',
            RegulatoryReport::TYPE_KYC  => 'regulatory.reports.kyc',
            RegulatoryReport::TYPE_AML  => 'regulatory.reports.aml',
            RegulatoryReport::TYPE_BSA  => 'regulatory.reports.bsa',
            RegulatoryReport::TYPE_OFAC => 'regulatory.reports.ofac',
        ];

        return $templates[$reportType] ?? 'regulatory.reports.default';
    }

    /**
     * Add Excel header.
     */
    protected function addExcelHeader($sheet, RegulatoryReport $report): void
    {
        $sheet->setCellValue('A1', $report->report_type . ' Report');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $sheet->setCellValue('A3', 'Report ID:');
        $sheet->setCellValue('B3', $report->report_id);

        $sheet->setCellValue('A4', 'Period:');
        $sheet->setCellValue('B4', $report->reporting_period_start->toDateString() . ' to ' . $report->reporting_period_end->toDateString());

        $sheet->setCellValue('A5', 'Generated:');
        $sheet->setCellValue('B5', $report->generated_at->toDateTimeString());

        $sheet->setCellValue('D3', 'Jurisdiction:');
        $sheet->setCellValue('E3', $report->jurisdiction);

        $sheet->setCellValue('D4', 'Status:');
        $sheet->setCellValue('E4', $report->getStatusLabel());
    }

    /**
     * Add Excel data.
     */
    protected function addExcelData($sheet, RegulatoryReport $report, array $data): void
    {
        $startRow = 7;

        // Add headers
        $headers = $this->extractCsvHeaders($report);
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $startRow, $header);
            $sheet->getStyle($col . $startRow)->getFont()->setBold(true);
            $col++;
        }

        // Add data rows
        $rows = $this->extractCsvRows($report, $data);
        $currentRow = $startRow + 1;
        foreach ($rows as $row) {
            $col = 'A';
            foreach ($row as $value) {
                $sheet->setCellValue($col . $currentRow, $value);
                $col++;
            }
            $currentRow++;
        }
    }

    /**
     * Get filename for report.
     */
    protected function getFilename(RegulatoryReport $report, string $extension): string
    {
        $type = strtolower($report->report_type);
        $date = $report->reporting_period_end->format('Y_m_d');

        return "regulatory/{$type}/{$report->report_id}_{$date}.{$extension}";
    }

    /**
     * Update report file information.
     */
    protected function updateReportFile(RegulatoryReport $report, string $filename, int $size): void
    {
        $report->update(
            [
            'file_path' => $filename,
            'file_size' => $size,
            'file_hash' => hash_file('sha256', Storage::path($filename)),
            ]
        );
    }

    /**
     * Get certification statement.
     */
    protected function getCertificationStatement(RegulatoryReport $report): string
    {
        return match ($report->report_type) {
            RegulatoryReport::TYPE_CTR => 'I certify that this Currency Transaction Report is complete and accurate to the best of my knowledge.',
            RegulatoryReport::TYPE_SAR => 'I certify that this Suspicious Activity Report contains all known information regarding the suspicious activity.',
            RegulatoryReport::TYPE_BSA => 'I certify compliance with all applicable Bank Secrecy Act requirements.',
            default                    => 'I certify that this report is complete and accurate.',
        };
    }
}
