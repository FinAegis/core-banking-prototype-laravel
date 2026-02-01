<?php

declare(strict_types=1);

namespace App\Domain\RegTech\Services;

use App\Domain\RegTech\Enums\Jurisdiction;

/**
 * Manages jurisdiction-specific regulatory configurations.
 */
class JurisdictionConfigurationService
{
    /**
     * Get configuration for a jurisdiction.
     *
     * @param Jurisdiction|string $jurisdiction
     * @return array<string, mixed>|null
     */
    public function getJurisdictionConfig(Jurisdiction|string $jurisdiction): ?array
    {
        $key = $jurisdiction instanceof Jurisdiction ? $jurisdiction->value : $jurisdiction;

        return config("regtech.jurisdictions.{$key}");
    }

    /**
     * Get all supported jurisdictions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllJurisdictions(): array
    {
        return config('regtech.jurisdictions', []);
    }

    /**
     * Get regulators for a jurisdiction.
     *
     * @param Jurisdiction|string $jurisdiction
     * @return array<string, array<string, mixed>>
     */
    public function getRegulators(Jurisdiction|string $jurisdiction): array
    {
        $config = $this->getJurisdictionConfig($jurisdiction);

        return $config['regulators'] ?? [];
    }

    /**
     * Get regulator configuration.
     *
     * @param Jurisdiction|string $jurisdiction
     * @param string $regulator
     * @return array<string, mixed>|null
     */
    public function getRegulatorConfig(Jurisdiction|string $jurisdiction, string $regulator): ?array
    {
        $regulators = $this->getRegulators($jurisdiction);

        return $regulators[$regulator] ?? null;
    }

    /**
     * Get supported report types for a regulator.
     *
     * @param Jurisdiction|string $jurisdiction
     * @param string $regulator
     * @return array<string>
     */
    public function getSupportedReportTypes(Jurisdiction|string $jurisdiction, string $regulator): array
    {
        $config = $this->getRegulatorConfig($jurisdiction, $regulator);

        return $config['reports'] ?? [];
    }

    /**
     * Get currency for jurisdiction.
     *
     * @param Jurisdiction|string $jurisdiction
     * @return string
     */
    public function getCurrency(Jurisdiction|string $jurisdiction): string
    {
        if ($jurisdiction instanceof Jurisdiction) {
            return $jurisdiction->currency();
        }

        $config = $this->getJurisdictionConfig($jurisdiction);

        return $config['currency'] ?? 'USD';
    }

    /**
     * Get timezone for jurisdiction.
     *
     * @param Jurisdiction|string $jurisdiction
     * @return string
     */
    public function getTimezone(Jurisdiction|string $jurisdiction): string
    {
        if ($jurisdiction instanceof Jurisdiction) {
            return $jurisdiction->timezone();
        }

        $config = $this->getJurisdictionConfig($jurisdiction);
        $regulators = $config['regulators'] ?? [];
        $firstRegulator = reset($regulators);

        return $firstRegulator['timezone'] ?? 'UTC';
    }

    /**
     * Get CTR threshold for jurisdiction.
     *
     * @param Jurisdiction|string $jurisdiction
     * @return float
     */
    public function getCtrThreshold(Jurisdiction|string $jurisdiction): float
    {
        $config = $this->getJurisdictionConfig($jurisdiction);

        return (float) ($config['ctr_threshold'] ?? 10000);
    }

    /**
     * Check if MiFID II applies to jurisdiction.
     *
     * @param Jurisdiction|string $jurisdiction
     * @return bool
     */
    public function isMifidApplicable(Jurisdiction|string $jurisdiction): bool
    {
        $key = $jurisdiction instanceof Jurisdiction ? $jurisdiction->value : $jurisdiction;

        return in_array($key, ['EU', 'UK']);
    }

    /**
     * Check if MiCA applies to jurisdiction.
     *
     * @param Jurisdiction|string $jurisdiction
     * @return bool
     */
    public function isMicaApplicable(Jurisdiction|string $jurisdiction): bool
    {
        $key = $jurisdiction instanceof Jurisdiction ? $jurisdiction->value : $jurisdiction;

        return $key === 'EU';
    }

    /**
     * Get API base URL for regulator.
     *
     * @param Jurisdiction|string $jurisdiction
     * @param string $regulator
     * @return string|null
     */
    public function getApiBaseUrl(Jurisdiction|string $jurisdiction, string $regulator): ?string
    {
        $config = $this->getRegulatorConfig($jurisdiction, $regulator);

        return $config['api_base'] ?? null;
    }

    /**
     * Get API key for regulator.
     *
     * @param Jurisdiction|string $jurisdiction
     * @param string $regulator
     * @return string|null
     */
    public function getApiKey(Jurisdiction|string $jurisdiction, string $regulator): ?string
    {
        $config = $this->getRegulatorConfig($jurisdiction, $regulator);

        return $config['api_key'] ?? null;
    }

    /**
     * Get jurisdiction by currency.
     *
     * @param string $currency
     * @return Jurisdiction|null
     */
    public function getJurisdictionByCurrency(string $currency): ?Jurisdiction
    {
        $mapping = config('regtech.cross_border.jurisdiction_mapping.currencies', []);

        $jurisdictionKey = $mapping[$currency] ?? null;

        if ($jurisdictionKey) {
            return Jurisdiction::tryFrom($jurisdictionKey);
        }

        return null;
    }

    /**
     * Determine applicable jurisdictions for a transaction.
     *
     * @param string $originCurrency
     * @param string $destinationCurrency
     * @param string|null $originCountry
     * @param string|null $destinationCountry
     * @return array<Jurisdiction>
     */
    public function determineApplicableJurisdictions(
        string $originCurrency,
        string $destinationCurrency,
        ?string $originCountry = null,
        ?string $destinationCountry = null
    ): array {
        $jurisdictions = [];

        // Add jurisdictions based on currency
        if ($origin = $this->getJurisdictionByCurrency($originCurrency)) {
            $jurisdictions[$origin->value] = $origin;
        }

        if ($destination = $this->getJurisdictionByCurrency($destinationCurrency)) {
            $jurisdictions[$destination->value] = $destination;
        }

        // Add jurisdictions based on country (simplified mapping)
        $countryMapping = [
            'US' => Jurisdiction::US,
            'DE' => Jurisdiction::EU,
            'FR' => Jurisdiction::EU,
            'IT' => Jurisdiction::EU,
            'ES' => Jurisdiction::EU,
            'NL' => Jurisdiction::EU,
            'GB' => Jurisdiction::UK,
            'SG' => Jurisdiction::SG,
        ];

        if ($originCountry && isset($countryMapping[$originCountry])) {
            $jurisdictions[$countryMapping[$originCountry]->value] = $countryMapping[$originCountry];
        }

        if ($destinationCountry && isset($countryMapping[$destinationCountry])) {
            $jurisdictions[$countryMapping[$destinationCountry]->value] = $countryMapping[$destinationCountry];
        }

        return array_values($jurisdictions);
    }

    /**
     * Get MiFID II configuration.
     *
     * @return array<string, mixed>
     */
    public function getMifidConfig(): array
    {
        return config('regtech.mifid', []);
    }

    /**
     * Get MiCA configuration.
     *
     * @return array<string, mixed>
     */
    public function getMicaConfig(): array
    {
        return config('regtech.mica', []);
    }

    /**
     * Get ML monitoring configuration.
     *
     * @return array<string, mixed>
     */
    public function getMlMonitoringConfig(): array
    {
        return config('regtech.ml_monitoring', []);
    }
}
