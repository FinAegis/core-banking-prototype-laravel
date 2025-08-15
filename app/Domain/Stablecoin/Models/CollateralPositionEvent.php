<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Models;

use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class CollateralPositionEvent extends EloquentStoredEvent
{
    protected $table = 'collateral_position_events';

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
