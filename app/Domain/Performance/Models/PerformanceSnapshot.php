<?php

declare(strict_types=1);

namespace App\Domain\Performance\Models;

use Spatie\EventSourcing\Snapshots\EloquentSnapshot;

class PerformanceSnapshot extends EloquentSnapshot
{
    protected $table = 'performance_snapshots';
}
