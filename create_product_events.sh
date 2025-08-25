#!/bin/bash

# Create Product domain events

cat > app/Domain/Product/Events/ProductCreated.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ProductCreated extends ShouldBeStored
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $description,
        public string $category,
        public string $type,
        public array $metadata,
        public DateTimeImmutable $createdAt
    ) {
    }
}
PHPEVENT

cat > app/Domain/Product/Events/ProductUpdated.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ProductUpdated extends ShouldBeStored
{
    public function __construct(
        public string $productId,
        public array $updates,
        public string $updatedBy,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
PHPEVENT

cat > app/Domain/Product/Events/ProductActivated.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ProductActivated extends ShouldBeStored
{
    public function __construct(
        public string $productId,
        public string $activatedBy,
        public DateTimeImmutable $activatedAt
    ) {
    }
}
PHPEVENT

cat > app/Domain/Product/Events/ProductDeactivated.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ProductDeactivated extends ShouldBeStored
{
    public function __construct(
        public string $productId,
        public string $reason,
        public string $deactivatedBy,
        public DateTimeImmutable $deactivatedAt
    ) {
    }
}
PHPEVENT

cat > app/Domain/Product/Events/FeatureAdded.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class FeatureAdded extends ShouldBeStored
{
    public function __construct(
        public string $productId,
        public array $feature,
        public string $addedBy,
        public DateTimeImmutable $addedAt
    ) {
    }
}
PHPEVENT

cat > app/Domain/Product/Events/FeatureRemoved.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class FeatureRemoved extends ShouldBeStored
{
    public function __construct(
        public string $productId,
        public string $featureCode,
        public string $removedBy,
        public DateTimeImmutable $removedAt
    ) {
    }
}
PHPEVENT

cat > app/Domain/Product/Events/PriceUpdated.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PriceUpdated extends ShouldBeStored
{
    public function __construct(
        public string $productId,
        public array $price,
        public string $updatedBy,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
PHPEVENT

echo "Product events created successfully!"
