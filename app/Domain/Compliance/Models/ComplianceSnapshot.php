<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Models;

use Spatie\EventSourcing\Snapshots\EloquentSnapshot;

class ComplianceSnapshot extends EloquentSnapshot
{
    protected $table = 'compliance_snapshots';

    public $timestamps = false;

    public $casts = [
        'state'      => 'array',
        'created_at' => 'datetime',
    ];
}
