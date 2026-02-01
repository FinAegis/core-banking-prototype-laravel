<?php

declare(strict_types=1);

namespace App\Domain\RegTech\Models;

use App\Domain\RegTech\Enums\Jurisdiction;
use App\Domain\Shared\Traits\UsesTenantConnection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $report_type
 * @property string $jurisdiction
 * @property string $regulator
 * @property string $frequency
 * @property int $deadline_days
 * @property string|null $deadline_time
 * @property Carbon|null $next_due_date
 * @property Carbon|null $last_filed_at
 * @property bool $is_active
 * @property bool $auto_generate
 * @property array|null $notification_settings
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder active()
 * @method static \Illuminate\Database\Eloquent\Builder jurisdiction(string $jurisdiction)
 * @method static \Illuminate\Database\Eloquent\Builder dueWithinDays(int $days)
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static create(array $attributes = [])
 */
class FilingSchedule extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use UsesTenantConnection;

    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_QUARTERLY = 'quarterly';

    public const FREQUENCY_ANNUALLY = 'annually';

    public const FREQUENCY_TRANSACTION = 'transaction';

    public const FREQUENCY_EVENT = 'event';

    protected $fillable = [
        'uuid',
        'name',
        'report_type',
        'jurisdiction',
        'regulator',
        'frequency',
        'deadline_days',
        'deadline_time',
        'next_due_date',
        'last_filed_at',
        'is_active',
        'auto_generate',
        'notification_settings',
        'metadata',
    ];

    protected $casts = [
        'deadline_days'         => 'integer',
        'next_due_date'         => 'datetime',
        'last_filed_at'         => 'datetime',
        'is_active'             => 'boolean',
        'auto_generate'         => 'boolean',
        'notification_settings' => 'array',
        'metadata'              => 'array',
    ];

    protected $attributes = [
        'is_active'     => true,
        'auto_generate' => false,
        'deadline_days' => 30,
        'regulator'     => 'default',
        'frequency'     => self::FREQUENCY_QUARTERLY,
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get valid frequencies.
     *
     * @return array<string>
     */
    public static function frequencies(): array
    {
        return [
            self::FREQUENCY_DAILY,
            self::FREQUENCY_WEEKLY,
            self::FREQUENCY_MONTHLY,
            self::FREQUENCY_QUARTERLY,
            self::FREQUENCY_ANNUALLY,
            self::FREQUENCY_TRANSACTION,
            self::FREQUENCY_EVENT,
        ];
    }

    /**
     * Scope to filter active schedules.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by jurisdiction.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $jurisdiction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeJurisdiction($query, string $jurisdiction)
    {
        return $query->where('jurisdiction', $jurisdiction);
    }

    /**
     * Scope to find schedules due within N days.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDueWithinDays($query, int $days)
    {
        return $query->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', now()->addDays($days))
            ->where('next_due_date', '>=', now());
    }

    /**
     * Get jurisdiction enum.
     */
    public function getJurisdictionEnum(): ?Jurisdiction
    {
        return Jurisdiction::tryFrom($this->jurisdiction);
    }

    /**
     * Calculate next due date based on frequency.
     *
     * @param Carbon|null $fromDate
     * @return Carbon
     */
    public function calculateNextDueDate(?Carbon $fromDate = null): Carbon
    {
        $from = $fromDate ?? now();

        $nextDue = match ($this->frequency) {
            self::FREQUENCY_DAILY     => $from->copy()->addDay(),
            self::FREQUENCY_WEEKLY    => $from->copy()->addWeek(),
            self::FREQUENCY_MONTHLY   => $from->copy()->addMonth(),
            self::FREQUENCY_QUARTERLY => $from->copy()->addMonths(3),
            self::FREQUENCY_ANNUALLY  => $from->copy()->addYear(),
            default                   => $from->copy()->addDays($this->deadline_days),
        };

        // Add deadline days offset
        if (in_array($this->frequency, [self::FREQUENCY_QUARTERLY, self::FREQUENCY_MONTHLY])) {
            $nextDue = $nextDue->endOfMonth()->addDays($this->deadline_days);
        }

        return $nextDue;
    }

    /**
     * Update next due date.
     */
    public function updateNextDueDate(): void
    {
        $this->update([
            'next_due_date' => $this->calculateNextDueDate(),
            'last_filed_at' => now(),
        ]);
    }

    /**
     * Check if filing is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->next_due_date && $this->next_due_date->isPast();
    }

    /**
     * Get days until due.
     */
    public function daysUntilDue(): ?int
    {
        if (! $this->next_due_date) {
            return null;
        }

        return (int) now()->diffInDays($this->next_due_date, false);
    }

    /**
     * Check if notification should be sent.
     *
     * @param int $warningDays
     * @return bool
     */
    public function shouldNotify(int $warningDays): bool
    {
        $daysUntilDue = $this->daysUntilDue();

        if ($daysUntilDue === null) {
            return false;
        }

        return $daysUntilDue <= $warningDays && $daysUntilDue >= 0;
    }
}
