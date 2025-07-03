<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemHealthCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'service',
        'check_type',
        'status',
        'response_time',
        'metadata',
        'error_message',
        'checked_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'checked_at' => 'datetime',
        'response_time' => 'float',
    ];

    /**
     * Get checks for a specific service
     */
    public function scopeForService($query, string $service)
    {
        return $query->where('service', $service);
    }

    /**
     * Get checks within a time range
     */
    public function scopeInTimeRange($query, $start, $end)
    {
        return $query->whereBetween('checked_at', [$start, $end]);
    }

    /**
     * Get only operational checks
     */
    public function scopeOperational($query)
    {
        return $query->where('status', 'operational');
    }

    /**
     * Get only failed checks
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['degraded', 'down']);
    }

    /**
     * Calculate uptime percentage for a service
     */
    public static function calculateUptime(string $service, int $days = 30)
    {
        $startDate = now()->subDays($days);
        
        $totalChecks = self::forService($service)
            ->where('checked_at', '>=', $startDate)
            ->count();
            
        if ($totalChecks === 0) {
            return 100.0;
        }
        
        $operationalChecks = self::forService($service)
            ->operational()
            ->where('checked_at', '>=', $startDate)
            ->count();
            
        return round(($operationalChecks / $totalChecks) * 100, 2);
    }

    /**
     * Get average response time for a service
     */
    public static function averageResponseTime(string $service, int $hours = 24)
    {
        $startDate = now()->subHours($hours);
        
        return self::forService($service)
            ->where('checked_at', '>=', $startDate)
            ->whereNotNull('response_time')
            ->avg('response_time') ?? 0;
    }

    /**
     * Get the latest status for each service
     */
    public static function getLatestStatuses()
    {
        return self::select('service', 'status', 'response_time', 'checked_at', 'error_message')
            ->whereIn('id', function ($query) {
                $query->select(\DB::raw('MAX(id)'))
                    ->from('system_health_checks')
                    ->groupBy('service');
            })
            ->get()
            ->keyBy('service');
    }
}