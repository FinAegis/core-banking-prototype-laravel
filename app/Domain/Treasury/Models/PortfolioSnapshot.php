<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Models;

use App\Domain\Shared\EventSourcing\TenantAwareSnapshot;

class PortfolioSnapshot extends TenantAwareSnapshot
{
    protected $table = 'portfolio_snapshots';

    public $timestamps = false;

    public $casts = [
        'state'      => 'array',
        'created_at' => 'datetime',
    ];
}
