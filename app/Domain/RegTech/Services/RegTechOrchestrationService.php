<?php

declare(strict_types=1);

namespace App\Domain\RegTech\Services;

use App\Domain\RegTech\Contracts\RegulatoryFilingAdapterInterface;
use App\Domain\RegTech\Enums\Jurisdiction;
use App\Domain\RegTech\Models\FilingSchedule;
use App\Domain\RegTech\Models\RegulatoryEndpoint;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates RegTech automation across jurisdictions and regulators.
 */
class RegTechOrchestrationService
{
    /** @var array<string, RegulatoryFilingAdapterInterface> */
    private array $adapters = [];

    public function __construct(
        private readonly JurisdictionConfigurationService $jurisdictionService,
        private readonly RegulatoryCalendarService $calendarService
    ) {
    }

    /**
     * Register a filing adapter.
     */
    public function registerAdapter(string $key, RegulatoryFilingAdapterInterface $adapter): void
    {
        $this->adapters[$key] = $adapter;
    }

    /**
     * Get registered adapter.
     */
    public function getAdapter(string $key): ?RegulatoryFilingAdapterInterface
    {
        return $this->adapters[$key] ?? null;
    }

    /**
     * Get all registered adapters.
     *
     * @return array<string, RegulatoryFilingAdapterInterface>
     */
    public function getAdapters(): array
    {
        return $this->adapters;
    }

    /**
     * Check if RegTech is enabled.
     */
    public function isEnabled(): bool
    {
        return config('regtech.enabled', true);
    }

    /**
     * Check if demo mode is enabled.
     */
    public function isDemoMode(): bool
    {
        return config('regtech.demo_mode', true);
    }

    /**
     * Submit a regulatory report.
     *
     * @param string $reportType
     * @param Jurisdiction|string $jurisdiction
     * @param array<string, mixed> $reportData
     * @param array<string, mixed> $metadata
     * @return array{success: bool, reference: string|null, errors: array<string>, details: array<string, mixed>}
     */
    public function submitReport(
        string $reportType,
        Jurisdiction|string $jurisdiction,
        array $reportData,
        array $metadata = []
    ): array {
        $jurisdictionKey = $jurisdiction instanceof Jurisdiction ? $jurisdiction->value : $jurisdiction;

        Log::info('RegTech: Submitting report', [
            'report_type'  => $reportType,
            'jurisdiction' => $jurisdictionKey,
        ]);

        if (! $this->isEnabled()) {
            return [
                'success'   => false,
                'reference' => null,
                'errors'    => ['RegTech automation is disabled'],
                'details'   => [],
            ];
        }

        // Find appropriate adapter
        $adapterKey = strtolower("{$jurisdictionKey}_{$reportType}");
        $adapter = $this->getAdapter($adapterKey);

        if (! $adapter) {
            // Try jurisdiction-level adapter
            $adapter = $this->getAdapter(strtolower($jurisdictionKey));
        }

        if ($this->isDemoMode()) {
            return $this->handleDemoSubmission($reportType, $jurisdictionKey, $reportData);
        }

        if (! $adapter) {
            Log::warning('RegTech: No adapter found for submission', [
                'report_type'  => $reportType,
                'jurisdiction' => $jurisdictionKey,
            ]);

            return [
                'success'   => false,
                'reference' => null,
                'errors'    => ["No adapter configured for {$jurisdictionKey} {$reportType}"],
                'details'   => [],
            ];
        }

        // Validate report
        $validation = $adapter->validateReport($reportType, $reportData);

        if (! $validation['valid']) {
            return [
                'success'   => false,
                'reference' => null,
                'errors'    => $validation['errors'],
                'details'   => ['validation_failed' => true],
            ];
        }

        // Submit report
        try {
            $result = $adapter->submitReport($reportType, $reportData, $metadata);

            Log::info('RegTech: Report submission result', [
                'success'   => $result['success'],
                'reference' => $result['reference'] ?? null,
            ]);

            return [
                'success'   => $result['success'],
                'reference' => $result['reference'],
                'errors'    => $result['errors'],
                'details'   => $result['response'],
            ];
        } catch (Throwable $e) {
            Log::error('RegTech: Report submission failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success'   => false,
                'reference' => null,
                'errors'    => [$e->getMessage()],
                'details'   => ['exception' => true],
            ];
        }
    }

    /**
     * Handle demo mode submission.
     *
     * @param string $reportType
     * @param string $jurisdiction
     * @param array<string, mixed> $reportData
     * @return array{success: bool, reference: string|null, errors: array<string>, details: array<string, mixed>}
     */
    private function handleDemoSubmission(
        string $reportType,
        string $jurisdiction,
        array $reportData
    ): array {
        // Simulate processing delay
        usleep(rand(100000, 500000)); // 100-500ms

        // Generate demo reference
        $reference = sprintf(
            'DEMO-%s-%s-%s',
            strtoupper($jurisdiction),
            strtoupper($reportType),
            strtoupper(uniqid())
        );

        Log::info('RegTech: Demo submission processed', [
            'reference' => $reference,
        ]);

        return [
            'success'   => true,
            'reference' => $reference,
            'errors'    => [],
            'details'   => [
                'demo_mode'    => true,
                'jurisdiction' => $jurisdiction,
                'report_type'  => $reportType,
                'submitted_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Check status of a submitted report.
     *
     * @param string $reference
     * @param Jurisdiction|string $jurisdiction
     * @return array{status: string, message: string, details: array<string, mixed>}
     */
    public function checkReportStatus(string $reference, Jurisdiction|string $jurisdiction): array
    {
        $jurisdictionKey = $jurisdiction instanceof Jurisdiction ? $jurisdiction->value : $jurisdiction;

        if ($this->isDemoMode()) {
            return $this->handleDemoStatusCheck($reference);
        }

        $adapter = $this->getAdapter(strtolower($jurisdictionKey));

        if (! $adapter) {
            return [
                'status'  => 'unknown',
                'message' => "No adapter configured for {$jurisdictionKey}",
                'details' => [],
            ];
        }

        try {
            return $adapter->checkStatus($reference);
        } catch (Throwable $e) {
            return [
                'status'  => 'error',
                'message' => $e->getMessage(),
                'details' => ['exception' => true],
            ];
        }
    }

    /**
     * Handle demo mode status check.
     *
     * @param string $reference
     * @return array{status: string, message: string, details: array<string, mixed>}
     */
    private function handleDemoStatusCheck(string $reference): array
    {
        $statuses = ['pending', 'processing', 'accepted', 'accepted'];
        $status = $statuses[array_rand($statuses)];

        return [
            'status'  => $status,
            'message' => "Demo status for {$reference}",
            'details' => [
                'demo_mode'  => true,
                'reference'  => $reference,
                'checked_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Get regulatory compliance summary.
     *
     * @param Jurisdiction|string|null $jurisdiction
     * @return array<string, mixed>
     */
    public function getComplianceSummary(Jurisdiction|string|null $jurisdiction = null): array
    {
        $jurisdictionKey = null;
        if ($jurisdiction) {
            $jurisdictionKey = $jurisdiction instanceof Jurisdiction ? $jurisdiction->value : $jurisdiction;
        }

        $upcomingDeadlines = $this->calendarService->getUpcomingDeadlines(30, $jurisdictionKey);
        $overdueFilings = $this->calendarService->getOverdueFilings($jurisdictionKey);

        return [
            'total_scheduled'     => FilingSchedule::active()->when($jurisdictionKey, fn ($q) => $q->jurisdiction($jurisdictionKey))->count(),
            'upcoming_deadlines'  => $upcomingDeadlines->count(),
            'overdue_filings'     => $overdueFilings->count(),
            'next_deadline'       => $upcomingDeadlines->first()?->next_due_date?->toIso8601String(),
            'jurisdiction'        => $jurisdictionKey ?? 'all',
            'demo_mode'           => $this->isDemoMode(),
            'adapters_registered' => count($this->adapters),
        ];
    }

    /**
     * Get endpoint health status.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getEndpointHealth(): array
    {
        $endpoints = RegulatoryEndpoint::active()->get();

        $health = [];

        foreach ($endpoints as $endpoint) {
            $health[$endpoint->name] = [
                'status'       => $endpoint->health_status,
                'last_check'   => $endpoint->last_health_check?->toIso8601String(),
                'is_sandbox'   => $endpoint->is_sandbox,
                'jurisdiction' => $endpoint->jurisdiction,
            ];
        }

        return $health;
    }

    /**
     * Run health checks on all endpoints.
     */
    public function runHealthChecks(): void
    {
        $endpoints = RegulatoryEndpoint::active()->get();

        foreach ($endpoints as $endpoint) {
            try {
                // Simple connectivity check
                $response = \Illuminate\Support\Facades\Http::timeout(10)
                    ->get($endpoint->buildUrl('/health'));

                if ($response->successful()) {
                    $endpoint->updateHealthStatus(RegulatoryEndpoint::HEALTH_HEALTHY);
                } else {
                    $endpoint->updateHealthStatus(
                        RegulatoryEndpoint::HEALTH_DEGRADED,
                        'HTTP ' . $response->status()
                    );
                }
            } catch (Throwable $e) {
                $endpoint->updateHealthStatus(
                    RegulatoryEndpoint::HEALTH_UNHEALTHY,
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * Get applicable regulations for a transaction.
     *
     * @param float $amount
     * @param string $currency
     * @param string $transactionType
     * @param array<string, mixed> $context
     * @return array<string, array<string, mixed>>
     */
    public function getApplicableRegulations(
        float $amount,
        string $currency,
        string $transactionType,
        array $context = []
    ): array {
        $regulations = [];

        // Determine jurisdiction from currency
        $jurisdiction = $this->jurisdictionService->getJurisdictionByCurrency($currency);

        if (! $jurisdiction) {
            return $regulations;
        }

        // Check CTR threshold
        $ctrThreshold = $this->jurisdictionService->getCtrThreshold($jurisdiction);

        if ($amount >= $ctrThreshold) {
            $regulations['ctr'] = [
                'name'         => 'Currency Transaction Report',
                'required'     => true,
                'threshold'    => $ctrThreshold,
                'amount'       => $amount,
                'jurisdiction' => $jurisdiction->value,
            ];
        }

        // Check MiFID II applicability
        if ($this->jurisdictionService->isMifidApplicable($jurisdiction)) {
            $regulations['mifid'] = [
                'name'         => 'MiFID II Transaction Reporting',
                'required'     => true,
                'deadline'     => 'T+1',
                'jurisdiction' => $jurisdiction->value,
            ];
        }

        // Check MiCA applicability for crypto
        if (
            $this->jurisdictionService->isMicaApplicable($jurisdiction) &&
            ($context['is_crypto'] ?? false)
        ) {
            $micaConfig = $this->jurisdictionService->getMicaConfig();

            $regulations['mica_travel_rule'] = [
                'name'         => 'MiCA Travel Rule',
                'required'     => $amount >= ($micaConfig['travel_rule']['threshold_eur'] ?? 1000),
                'threshold'    => $micaConfig['travel_rule']['threshold_eur'] ?? 1000,
                'jurisdiction' => $jurisdiction->value,
            ];
        }

        return $regulations;
    }
}
