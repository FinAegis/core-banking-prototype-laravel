<?php

namespace App\Domain\Lending\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;

class LendingEvent extends TenantAwareStoredEvent
{
    protected $table = 'lending_events';

    public $timestamps = false;

    public $casts = [
        'event_properties' => 'array',
        'meta_data'        => 'array',
        'created_at'       => 'datetime',
    ];

    protected $fillable = [
        'aggregate_uuid',
        'aggregate_version',
        'event_version',
        'event_class',
        'event_properties',
        'meta_data',
        'created_at',
    ];
}
