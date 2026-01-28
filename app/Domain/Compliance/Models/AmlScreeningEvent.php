<?php

/**
 * AML Screening Event Model.
 */

namespace App\Domain\Compliance\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Model for AML screening events stored in the event sourcing table.
 */
class AmlScreeningEvent extends TenantAwareStoredEvent
{
    use HasFactory;

    public $table = 'aml_screening_events';
}
