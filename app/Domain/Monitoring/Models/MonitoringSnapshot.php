<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Models;

use Spatie\EventSourcing\Snapshots\EloquentSnapshot;

class MonitoringSnapshot extends EloquentSnapshot
{
    public $table = 'monitoring_snapshots';

    public $guarded = [];
}
