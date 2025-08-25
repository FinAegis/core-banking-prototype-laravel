#!/bin/bash

# Create Performance domain events

cat > app/Domain/Performance/Events/MetricRecorded.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\Performance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MetricRecorded extends ShouldBeStored
{
    public function __construct(
        public string $metricId,
        public string $systemId,
        public string $name,
        public float $value,
        public string $type,
        public array $tags,
        public DateTimeImmutable $timestamp
    ) {
    }
}
PHPEVENT

cat > app/Domain/Performance/Events/ThresholdExceeded.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\Performance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ThresholdExceeded extends ShouldBeStored
{
    public function __construct(
        public string $metricId,
        public string $systemId,
        public string $metricName,
        public float $value,
        public float $threshold,
        public string $severity,
        public DateTimeImmutable $timestamp
    ) {
    }
}
PHPEVENT

cat > app/Domain/Performance/Events/PerformanceAlertTriggered.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\Performance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PerformanceAlertTriggered extends ShouldBeStored
{
    public function __construct(
        public string $metricId,
        public string $systemId,
        public string $alertType,
        public string $metricName,
        public float $value,
        public float $threshold,
        public string $severity,
        public string $message,
        public DateTimeImmutable $timestamp
    ) {
    }
}
PHPEVENT

cat > app/Domain/Performance/Events/PerformanceReportGenerated.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\Performance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PerformanceReportGenerated extends ShouldBeStored
{
    public function __construct(
        public string $metricId,
        public string $systemId,
        public string $reportType,
        public array $reportData,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public DateTimeImmutable $generatedAt
    ) {
    }
}
PHPEVENT

echo "Performance events created successfully!"
