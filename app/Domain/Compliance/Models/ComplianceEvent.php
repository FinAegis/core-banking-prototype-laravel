<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;

class ComplianceEvent extends TenantAwareStoredEvent
{
    protected $table = 'compliance_events';

    public $timestamps = false;

    public $casts = [
        'event_properties' => 'array',
        'meta_data'        => 'array',
        'created_at'       => 'datetime',
    ];
}
