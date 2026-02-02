<?php

declare(strict_types=1);

namespace App\Domain\Privacy\ValueObjects;

use JsonSerializable;

/**
 * Value object representing a ZK circuit's SRS (Structured Reference String) metadata.
 *
 * SRS files are required for ZK proof generation on mobile devices.
 * This object describes a circuit's download requirements.
 */
final readonly class SrsCircuit implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $version,
        public int $size,
        public bool $required,
        public string $downloadUrl,
        public string $checksum,
        public string $checksumAlgorithm = 'sha256',
    ) {
    }

    /**
     * Create from config array.
     *
     * @param array{size: int, required: bool, checksum?: string} $config
     */
    public static function fromConfig(string $name, array $config, string $cdnBaseUrl, string $version): self
    {
        $checksum = $config['checksum'] ?? hash('sha256', "{$name}_{$version}");

        return new self(
            name: $name,
            version: $version,
            size: $config['size'],
            required: $config['required'],
            downloadUrl: "{$cdnBaseUrl}/{$version}/{$name}.srs",
            checksum: $checksum,
        );
    }

    /**
     * Get the size in human-readable format.
     */
    public function getHumanReadableSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'name'               => $this->name,
            'version'            => $this->version,
            'size'               => $this->size,
            'size_human'         => $this->getHumanReadableSize(),
            'required'           => $this->required,
            'download_url'       => $this->downloadUrl,
            'checksum'           => $this->checksum,
            'checksum_algorithm' => $this->checksumAlgorithm,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
