<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceMetric extends Model
{
    protected $fillable = [
        'metric_id',
        'system_id',
        'name',
        'value',
        'type',
        'tags',
        'recorded_at',
    ];

    protected $casts = [
        'value'       => 'float',
        'tags'        => 'array',
        'recorded_at' => 'datetime',
    ];

    /**
     * Scope to filter by system.
     */
    public function scopeForSystem($query, string $systemId)
    {
        return $query->where('system_id', $systemId);
    }

    /**
     * Scope to filter by metric name.
     */
    public function scopeByName($query, string $name)
    {
        return $query->where('name', $name);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get recent metrics.
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('recorded_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Scope to get metrics in date range.
     */
    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }
}
