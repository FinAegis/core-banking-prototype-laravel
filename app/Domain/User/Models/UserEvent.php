<?php

declare(strict_types=1);

namespace App\Domain\User\Models;

use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class UserEvent extends EloquentStoredEvent
{
    protected $table = 'user_events';
}
