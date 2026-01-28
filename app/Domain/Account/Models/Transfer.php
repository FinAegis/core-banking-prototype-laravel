<?php

namespace App\Domain\Account\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;
use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transfer extends TenantAwareStoredEvent
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
