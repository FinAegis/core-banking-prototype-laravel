<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Models\Tenant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a new tenant is provisioned.
 */
class TenantCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $plan,
    ) {
    }
}
