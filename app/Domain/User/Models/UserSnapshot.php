<?php

declare(strict_types=1);

namespace App\Domain\User\Models;

use Spatie\EventSourcing\Snapshots\EloquentSnapshot;

class UserSnapshot extends EloquentSnapshot
{
    protected $table = 'user_snapshots';
}
