<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Models;

use Spatie\EventSourcing\Snapshots\EloquentSnapshot;

class PortfolioSnapshot extends EloquentSnapshot
{
    protected $table = 'portfolio_snapshots';

    public $timestamps = false;

    public $casts = [
        'state'      => 'array',
        'created_at' => 'datetime',
    ];
}
