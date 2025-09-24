<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceAlert extends Model
{
    protected $fillable = [
        'metric_id',
        'system_id',
        'alert_type',
        'metric_name',
        'value',
        'threshold',
        'severity',
        'message',
        'triggered_at',
        'resolved_at',
    ];

    protected $casts = [
        'value'        => 'float',
        'threshold'    => 'float',
        'triggered_at' => 'datetime',
        'resolved_at'  => 'datetime',
    ];

    /**
     * Scope to get unresolved alerts.
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope to filter by severity.
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to get critical alerts.
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Mark alert as resolved.
     */
    public function resolve(): void
    {
        $this->update(['resolved_at' => now()]);
    }

    /**
     * Check if alert is critical.
     */
    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }
}
