<?php

declare(strict_types=1);

namespace App\Domain\Treasury\ValueObjects;

use InvalidArgumentException;

final class AssetAllocation
{
    private string $assetClass;

    private float $targetWeight;

    private float $currentWeight;

    private float $drift;

    public function __construct(
        string $assetClass,
        float $targetWeight,
        float $currentWeight = 0.0,
        float $drift = 0.0
    ) {
        if (empty($assetClass)) {
            throw new InvalidArgumentException('Asset class cannot be empty');
        }

        if ($targetWeight < 0.0 || $targetWeight > 100.0) {
            throw new InvalidArgumentException('Target weight must be between 0 and 100');
        }

        if ($currentWeight < 0.0 || $currentWeight > 100.0) {
            throw new InvalidArgumentException('Current weight must be between 0 and 100');
        }

        $this->assetClass = $assetClass;
        $this->targetWeight = $targetWeight;
        $this->currentWeight = $currentWeight;
        $this->drift = $drift !== 0.0 ? $drift : abs($currentWeight - $targetWeight);
    }

    public function getAssetClass(): string
    {
        return $this->assetClass;
    }

    public function getTargetWeight(): float
    {
        return $this->targetWeight;
    }

    public function getCurrentWeight(): float
    {
        return $this->currentWeight;
    }

    public function getDrift(): float
    {
        return $this->drift;
    }

    public function updateCurrentWeight(float $currentWeight): self
    {
        if ($currentWeight < 0.0 || $currentWeight > 100.0) {
            throw new InvalidArgumentException('Current weight must be between 0 and 100');
        }

        return new self(
            $this->assetClass,
            $this->targetWeight,
            $currentWeight,
            abs($currentWeight - $this->targetWeight)
        );
    }

    public function isWithinDriftThreshold(float $threshold): bool
    {
        return $this->drift <= $threshold;
    }

    public function needsRebalancing(float $threshold): bool
    {
        return $this->drift > $threshold;
    }

    public function toArray(): array
    {
        return [
            'assetClass'    => $this->assetClass,
            'targetWeight'  => $this->targetWeight,
            'currentWeight' => $this->currentWeight,
            'drift'         => $this->drift,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['assetClass'],
            $data['targetWeight'],
            $data['currentWeight'] ?? 0.0,
            $data['drift'] ?? 0.0
        );
    }

    public function equals(self $other): bool
    {
        return $this->assetClass === $other->assetClass &&
               abs($this->targetWeight - $other->targetWeight) < 0.01 &&
               abs($this->currentWeight - $other->currentWeight) < 0.01;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s: %.2f%% target, %.2f%% current (%.2f%% drift)',
            $this->assetClass,
            $this->targetWeight,
            $this->currentWeight,
            $this->drift
        );
    }
}
