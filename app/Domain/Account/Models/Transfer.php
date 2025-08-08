<?php

namespace App\Domain\Account\Models;

use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class Transfer extends EloquentStoredEvent
{
    use HasFactory;

    public $table = 'transfers';

    /**
     * Create a new factory instance for the model.
     *
     * @return TransferFactory
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return TransferFactory::new();
    }
}
