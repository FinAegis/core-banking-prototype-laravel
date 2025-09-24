<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceReport extends Model
{
    protected $fillable = [
        'report_id',
        'system_id',
        'report_type',
        'report_data',
        'from_date',
        'to_date',
        'generated_at',
    ];

    protected $casts = [
        'report_data'  => 'array',
        'from_date'    => 'datetime',
        'to_date'      => 'datetime',
        'generated_at' => 'datetime',
    ];

    /**
     * Scope to filter by system.
     */
    public function scopeForSystem($query, string $systemId)
    {
        return $query->where('system_id', $systemId);
    }

    /**
     * Scope to filter by report type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('report_type', $type);
    }

    /**
     * Get report summary.
     */
    public function getSummary(): array
    {
        return $this->report_data['summary'] ?? [];
    }

    /**
     * Get report metrics.
     */
    public function getMetrics(): array
    {
        return $this->report_data['metrics'] ?? [];
    }
}
