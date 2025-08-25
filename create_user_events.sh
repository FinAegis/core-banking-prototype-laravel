#!/bin/bash

# Create all User domain events

cat > app/Domain/User/Events/UserProfileVerified.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UserProfileVerified extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public string $verificationType,
        public string $verifiedBy,
        public DateTimeImmutable $verifiedAt
    ) {
    }
}
PHPEVENT

cat > app/Domain/User/Events/UserProfileSuspended.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UserProfileSuspended extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public string $reason,
        public string $suspendedBy,
        public DateTimeImmutable $suspendedAt
    ) {
    }
}
PHPEVENT

cat > app/Domain/User/Events/UserProfileDeleted.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UserProfileDeleted extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public string $reason,
        public string $deletedBy,
        public DateTimeImmutable $deletedAt
    ) {
    }
}
PHPEVENT

cat > app/Domain/User/Events/UserPreferencesUpdated.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UserPreferencesUpdated extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public array $preferences,
        public string $updatedBy,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
PHPEVENT

cat > app/Domain/User/Events/NotificationPreferencesUpdated.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class NotificationPreferencesUpdated extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public array $preferences,
        public string $updatedBy,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
PHPEVENT

cat > app/Domain/User/Events/PrivacySettingsUpdated.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PrivacySettingsUpdated extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public array $settings,
        public string $updatedBy,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
PHPEVENT

cat > app/Domain/User/Events/UserActivityTracked.php << 'PHPEVENT'
<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UserActivityTracked extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public string $activity,
        public array $context,
        public DateTimeImmutable $trackedAt
    ) {
    }
}
PHPEVENT

echo "User events created successfully!"
