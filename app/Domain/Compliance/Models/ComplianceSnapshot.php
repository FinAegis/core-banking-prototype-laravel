<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Models;

use App\Domain\Shared\EventSourcing\TenantAwareSnapshot;

class ComplianceSnapshot extends TenantAwareSnapshot
{
    protected $table = 'compliance_snapshots';

    public $timestamps = false;

    public $casts = [
        'state'      => 'array',
        'created_at' => 'datetime',
    ];
}
