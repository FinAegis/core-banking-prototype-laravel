<?php

declare(strict_types=1);

namespace App\Domain\Performance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class PerformanceMetric extends Model
{
    use UsesTenantConnection;

    protected $table = 'performance_metrics';

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

    public function scopeByName($query, string $name)
    {
        return $query->where('name', $name);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('recorded_at', '>=', now()->subMinutes($minutes));
    }

    public function scopeBySystem($query, string $systemId)
    {
        return $query->where('system_id', $systemId);
    }

    public function getFormattedValue(): string
    {
        return match ($this->type) {
            'percentage', 'cpu_usage', 'error_rate' => number_format($this->value, 1) . '%',
            'bytes', 'memory_usage', 'disk_usage' => $this->formatBytes($this->value),
            'milliseconds', 'latency', 'timer' => number_format($this->value, 2) . 'ms',
            'throughput' => number_format($this->value, 0) . ' ops/s',
            default      => number_format($this->value, 2),
        };
    }

    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, 2) . ' ' . $units[$i];
    }
}
