<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Models;

use Spatie\EventSourcing\Snapshots\EloquentSnapshot;

class CollateralPositionSnapshot extends EloquentSnapshot
{
    protected $table = 'collateral_position_snapshots';

    public $timestamps = false;

    public $casts = [
        'state'      => 'array',
        'created_at' => 'datetime',
    ];

    protected $fillable = [
        'aggregate_uuid',
        'aggregate_version',
        'state',
        'created_at',
    ];
}
