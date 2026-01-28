<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;

class PortfolioEvent extends TenantAwareStoredEvent
{
    protected $table = 'portfolio_events';

    public $timestamps = false;

    public $casts = [
        'event_properties' => 'array',
        'meta_data'        => 'array',
        'created_at'       => 'datetime',
    ];
}
