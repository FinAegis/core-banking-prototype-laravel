<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\ValueObjects;

enum MetricType: string
{
    case COUNTER = 'counter';
    case GAUGE = 'gauge';
    case HISTOGRAM = 'histogram';
    case SUMMARY = 'summary';

    public function description(): string
    {
        return match ($this) {
            self::COUNTER   => 'A cumulative metric that only increases',
            self::GAUGE     => 'A metric that can go up or down',
            self::HISTOGRAM => 'A metric that samples observations and counts them in buckets',
            self::SUMMARY   => 'A metric that calculates quantiles over a sliding time window',
        };
    }

    public function isAccumulating(): bool
    {
        return in_array($this, [self::COUNTER, self::HISTOGRAM, self::SUMMARY]);
    }
}
