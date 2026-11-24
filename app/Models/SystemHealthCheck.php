<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static firstOrFail(array $columns = ['*'])
 * @method static int count(string $columns = '*')
 * @method static bool exists()
 * @method static static create(array $attributes = [])
 * @method static static updateOrCreate(array $attributes, array $values = [])
 */
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
        'metadata'      => 'array',
        'checked_at'    => 'datetime',
        'response_time' => 'float',
    ];

    /**
     * Get checks for a specific service.
     */
    public function scopeForService($query, string $service)
    {
        return $query->where('service', $service);
    }

    /**
     * Get checks within a time range.
     */
    public function scopeInTimeRange($query, $start, $end)
    {
        return $query->whereBetween('checked_at', [$start, $end]);
    }

    /**
     * Get only operational checks.
     */
    public function scopeOperational($query)
    {
        return $query->where('status', 'operational');
    }

    /**
     * Get only failed checks.
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['degraded', 'down']);
    }

    /**
     * Share of operational checks for a service, in percent. Returns null
     * when the service has no recorded check in the period, so no figure is
     * shown for a service that nothing checks.
     */
    public static function calculateUptime(string $service, int $days = 30): ?float
    {
        $startDate = now()->subDays($days);

        $totalChecks = self::forService($service)
            ->where('checked_at', '>=', $startDate)
            ->count();

        if ($totalChecks === 0) {
            return null;
        }

        $operationalChecks = self::forService($service)
            ->operational()
            ->where('checked_at', '>=', $startDate)
            ->count();

        return round(($operationalChecks / $totalChecks) * 100, 2);
    }

    /**
     * Get average response time for a service.
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
     * Get the latest status for each service.
     */
    public static function getLatestStatuses()
    {
        return self::select('service', 'status', 'response_time', 'checked_at', 'error_message')
            ->whereIn(
                'id',
                function ($query) {
                    $query->selectRaw('MAX(id)')
                        ->from('system_health_checks')
                        ->groupBy('service');
                }
            )
            ->get()
            ->keyBy('service');
    }

    /**
     * Get the activity logs for this model.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function logs()
    {
        return $this->morphMany(\App\Domain\Activity\Models\Activity::class, 'subject');
    }
}
