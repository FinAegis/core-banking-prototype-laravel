<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\ValueObjects\SrsCircuit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing SRS (Structured Reference String) manifests.
 *
 * SRS files are required for ZK proof generation on mobile devices.
 * This service provides the manifest of available circuits and tracks downloads.
 */
class SrsManifestService
{
    private const DEFAULT_VERSION = '1.0.0';

    private const DEFAULT_CDN_BASE_URL = 'https://cdn.finaegis.com/srs';

    /**
     * Get the current SRS manifest version.
     */
    public function getVersion(): string
    {
        return (string) (config('privacy.srs.version') ?? self::DEFAULT_VERSION);
    }

    /**
     * Get the CDN base URL for SRS downloads.
     */
    public function getCdnBaseUrl(): string
    {
        return (string) (config('privacy.srs.cdn_base_url') ?? self::DEFAULT_CDN_BASE_URL);
    }

    /**
     * Get all available circuits.
     *
     * @return Collection<int, SrsCircuit>
     */
    public function getCircuits(): Collection
    {
        /** @var array<string, array{size: int, required: bool, checksum?: string}> $circuits */
        $circuits = config('privacy.srs.circuits', []);
        $version = $this->getVersion();
        $cdnBaseUrl = $this->getCdnBaseUrl();

        return collect($circuits)->map(function (array $config, string $name) use ($version, $cdnBaseUrl): SrsCircuit {
            /** @var array{size: int, required: bool, checksum?: string} $config */
            return SrsCircuit::fromConfig($name, $config, $cdnBaseUrl, $version);
        })->values();
    }

    /**
     * Get only required circuits.
     *
     * @return Collection<int, SrsCircuit>
     */
    public function getRequiredCircuits(): Collection
    {
        return $this->getCircuits()->filter(fn (SrsCircuit $circuit) => $circuit->required);
    }

    /**
     * Get a specific circuit by name.
     */
    public function getCircuit(string $name): ?SrsCircuit
    {
        return $this->getCircuits()->first(fn (SrsCircuit $circuit) => $circuit->name === $name);
    }

    /**
     * Get the total download size for all circuits.
     */
    public function getTotalSize(): int
    {
        return $this->getCircuits()->sum(fn (SrsCircuit $circuit) => $circuit->size);
    }

    /**
     * Get the total download size for required circuits only.
     */
    public function getRequiredSize(): int
    {
        return $this->getRequiredCircuits()->sum(fn (SrsCircuit $circuit) => $circuit->size);
    }

    /**
     * Track that a user has downloaded an SRS file.
     *
     * This is used for analytics and to track client-side proving capability.
     *
     * @param array<string> $circuitNames
     */
    public function trackDownload(User $user, array $circuitNames, string $deviceInfo = ''): void
    {
        Log::info('SRS download tracked', [
            'user_id'     => $user->id,
            'circuits'    => $circuitNames,
            'device_info' => $deviceInfo,
            'srs_version' => $this->getVersion(),
        ]);

        // In production, you might want to:
        // - Store this in a database table for analytics
        // - Update user preferences to indicate they can do client-side proving
        // - Track download completion rates
    }

    /**
     * Check if a user has the required SRS files downloaded.
     *
     * This would typically be tracked in user preferences or a dedicated table.
     * For now, returns false as the implementation is client-side.
     */
    public function hasRequiredSrs(User $user): bool
    {
        // In a real implementation, this would check a user_srs_downloads table
        // or user preferences to see if they've reported downloading the required files
        return false;
    }

    /**
     * Get the full manifest for API response.
     *
     * @return array<string, mixed>
     */
    public function getManifest(): array
    {
        $circuits = $this->getCircuits();

        return [
            'version'        => $this->getVersion(),
            'cdn_base_url'   => $this->getCdnBaseUrl(),
            'total_size'     => $this->getTotalSize(),
            'required_size'  => $this->getRequiredSize(),
            'circuits'       => $circuits->map(fn (SrsCircuit $c) => $c->toArray())->toArray(),
            'required_count' => $this->getRequiredCircuits()->count(),
            'total_count'    => $circuits->count(),
        ];
    }
}
