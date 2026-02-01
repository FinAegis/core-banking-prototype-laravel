<?php

declare(strict_types=1);

namespace App\Domain\RegTech\Contracts;

use App\Domain\RegTech\Enums\Jurisdiction;

/**
 * Interface for regulatory filing adapters.
 * Each jurisdiction's regulatory body will have its own implementation.
 */
interface RegulatoryFilingAdapterInterface
{
    /**
     * Get the adapter name.
     */
    public function getName(): string;

    /**
     * Get the jurisdiction this adapter handles.
     */
    public function getJurisdiction(): Jurisdiction;

    /**
     * Get supported report types.
     *
     * @return array<string>
     */
    public function getSupportedReportTypes(): array;

    /**
     * Submit a report to the regulatory authority.
     *
     * @param string $reportType
     * @param array<string, mixed> $reportData
     * @param array<string, mixed> $metadata
     * @return array{success: bool, reference: string|null, errors: array<string>, response: array<string, mixed>}
     */
    public function submitReport(string $reportType, array $reportData, array $metadata = []): array;

    /**
     * Check the status of a submitted report.
     *
     * @param string $reference
     * @return array{status: string, message: string, details: array<string, mixed>}
     */
    public function checkStatus(string $reference): array;

    /**
     * Validate report data before submission.
     *
     * @param string $reportType
     * @param array<string, mixed> $reportData
     * @return array{valid: bool, errors: array<string>}
     */
    public function validateReport(string $reportType, array $reportData): array;

    /**
     * Get the API endpoint for this adapter.
     */
    public function getApiEndpoint(): string;

    /**
     * Check if the adapter is configured and available.
     */
    public function isAvailable(): bool;

    /**
     * Check if sandbox/demo mode is enabled.
     */
    public function isSandboxMode(): bool;
}
