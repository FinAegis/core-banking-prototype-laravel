<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Models;

use Spatie\EventSourcing\Snapshots\EloquentSnapshot;

class TreasurySnapshot extends EloquentSnapshot
{
    protected $table = 'treasury_snapshots';

    public $timestamps = false;

    public $casts = [
        'state'      => 'array',
        'created_at' => 'datetime',
    ];
}
