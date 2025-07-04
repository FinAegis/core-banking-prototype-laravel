<?php

namespace App\Domain\Lending\Repositories;

use App\Models\LendingEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

class LendingEventRepository extends EloquentStoredEventRepository
{
    protected string $storedEventModel;
    
    public function __construct()
    {
        $this->storedEventModel = LendingEvent::class;
        
        parent::__construct();
    }
}