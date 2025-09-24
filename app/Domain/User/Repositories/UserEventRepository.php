<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Domain\User\Models\UserEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

class UserEventRepository extends EloquentStoredEventRepository
{
    protected string $storedEventModel = UserEvent::class;
}
