<?php

namespace App\Domain\Lending\ValueObjects;

class RiskRating
{
    private const VALID_RATINGS = ['A', 'B', 'C', 'D', 'E', 'F'];
    
    public function __construct(
        public readonly string $rating,
        public readonly float $defaultProbability,
        public readonly array $riskFactors
    ) {
        if (!in_array($rating, self::VALID_RATINGS)) {
            throw new \InvalidArgumentException('Invalid risk rating. Must be A-F');
        }
        
        if ($defaultProbability < 0 || $defaultProbability > 1) {
            throw new \InvalidArgumentException('Default probability must be between 0 and 1');
        }
    }
    
    public function isLowRisk(): bool
    {
        return in_array($this->rating, ['A', 'B']);
    }
    
    public function isMediumRisk(): bool
    {
        return in_array($this->rating, ['C', 'D']);
    }
    
    public function isHighRisk(): bool
    {
        return in_array($this->rating, ['E', 'F']);
    }
    
    public function getInterestRateMultiplier(): float
    {
        return match($this->rating) {
            'A' => 1.0,
            'B' => 1.2,
            'C' => 1.5,
            'D' => 2.0,
            'E' => 2.5,
            'F' => 3.0,
        };
    }
    
    public function toArray(): array
    {
        return [
            'rating' => $this->rating,
            'default_probability' => $this->defaultProbability,
            'risk_factors' => $this->riskFactors,
        ];
    }
}