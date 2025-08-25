<?php

declare(strict_types=1);

namespace App\Domain\Performance\ValueObjects;

enum MetricType: string
{
    case COUNTER = 'counter';
    case GAUGE = 'gauge';
    case HISTOGRAM = 'histogram';
    case TIMER = 'timer';
    case RATE = 'rate';
    case THROUGHPUT = 'throughput';
    case LATENCY = 'latency';
    case ERROR_RATE = 'error_rate';
    case CPU_USAGE = 'cpu_usage';
    case MEMORY_USAGE = 'memory_usage';
    case DISK_USAGE = 'disk_usage';
    case NETWORK_IO = 'network_io';

    public function getUnit(): string
    {
        return match ($this) {
            self::COUNTER      => 'count',
            self::GAUGE        => 'value',
            self::HISTOGRAM    => 'distribution',
            self::TIMER        => 'milliseconds',
            self::RATE         => 'per_second',
            self::THROUGHPUT   => 'operations_per_second',
            self::LATENCY      => 'milliseconds',
            self::ERROR_RATE   => 'percentage',
            self::CPU_USAGE    => 'percentage',
            self::MEMORY_USAGE => 'bytes',
            self::DISK_USAGE   => 'bytes',
            self::NETWORK_IO   => 'bytes_per_second',
        };
    }

    public function isPercentage(): bool
    {
        return in_array($this, [self::ERROR_RATE, self::CPU_USAGE], true);
    }

    public function isTime(): bool
    {
        return in_array($this, [self::TIMER, self::LATENCY], true);
    }
}
