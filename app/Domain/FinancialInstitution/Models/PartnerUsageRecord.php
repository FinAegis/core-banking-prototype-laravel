<?php

declare(strict_types=1);

namespace App\Domain\FinancialInstitution\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $partner_id
 * @property \Carbon\Carbon $usage_date
 * @property string $period_type
 * @property int $api_calls
 * @property int $api_calls_success
 * @property int $api_calls_failed
 * @property array|null $endpoint_breakdown
 * @property int $transactions_count
 * @property float $transactions_volume
 * @property int $webhooks_sent
 * @property int $webhooks_failed
 * @property int $widget_loads
 * @property int $widget_conversions
 * @property int $sdk_downloads
 * @property float|null $avg_response_time_ms
 * @property float|null $p99_response_time_ms
 * @property array|null $error_breakdown
 * @property bool $is_billable
 * @property float $overage_amount_usd
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property FinancialInstitutionPartner $partner
 *
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder forDate(\Carbon\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder daily()
 * @method static \Illuminate\Database\Eloquent\Builder billable()
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static create(array $attributes = [])
 */
class PartnerUsageRecord extends Model
{
    use HasFactory;
    use HasUuids;
    use UsesTenantConnection;

    protected $fillable = [
        'uuid',
        'partner_id',
        'usage_date',
        'period_type',
        'api_calls',
        'api_calls_success',
        'api_calls_failed',
        'endpoint_breakdown',
        'transactions_count',
        'transactions_volume',
        'webhooks_sent',
        'webhooks_failed',
        'widget_loads',
        'widget_conversions',
        'sdk_downloads',
        'avg_response_time_ms',
        'p99_response_time_ms',
        'error_breakdown',
        'is_billable',
        'overage_amount_usd',
    ];

    protected $casts = [
        'usage_date'           => 'date',
        'endpoint_breakdown'   => 'array',
        'error_breakdown'      => 'array',
        'is_billable'          => 'boolean',
        'api_calls'            => 'integer',
        'api_calls_success'    => 'integer',
        'api_calls_failed'     => 'integer',
        'transactions_count'   => 'integer',
        'transactions_volume'  => 'decimal:2',
        'webhooks_sent'        => 'integer',
        'webhooks_failed'      => 'integer',
        'widget_loads'         => 'integer',
        'widget_conversions'   => 'integer',
        'sdk_downloads'        => 'integer',
        'avg_response_time_ms' => 'decimal:2',
        'p99_response_time_ms' => 'decimal:2',
        'overage_amount_usd'   => 'decimal:2',
    ];

    protected $attributes = [
        'period_type'         => 'daily',
        'api_calls'           => 0,
        'api_calls_success'   => 0,
        'api_calls_failed'    => 0,
        'transactions_count'  => 0,
        'transactions_volume' => 0,
        'webhooks_sent'       => 0,
        'webhooks_failed'     => 0,
        'widget_loads'        => 0,
        'widget_conversions'  => 0,
        'sdk_downloads'       => 0,
        'is_billable'         => true,
        'overage_amount_usd'  => 0,
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
     * Get the partner that owns this usage record.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitutionPartner::class, 'partner_id');
    }

    /**
     * Scope to filter by date.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('usage_date', $date->toDateString());
    }

    /**
     * Scope to filter daily records.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDaily($query)
    {
        return $query->where('period_type', 'daily');
    }

    /**
     * Scope to filter billable records.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    /**
     * Get success rate percentage.
     */
    public function getSuccessRate(): float
    {
        if ($this->api_calls === 0) {
            return 100.0;
        }

        return round(($this->api_calls_success / $this->api_calls) * 100, 2);
    }

    /**
     * Get widget conversion rate percentage.
     */
    public function getWidgetConversionRate(): float
    {
        if ($this->widget_loads === 0) {
            return 0.0;
        }

        return round(($this->widget_conversions / $this->widget_loads) * 100, 2);
    }

    /**
     * Get webhook success rate percentage.
     */
    public function getWebhookSuccessRate(): float
    {
        $total = $this->webhooks_sent + $this->webhooks_failed;

        if ($total === 0) {
            return 100.0;
        }

        return round(($this->webhooks_sent / $total) * 100, 2);
    }

    /**
     * Increment API calls.
     *
     * @param int $count
     * @param bool $success
     * @param string|null $endpoint
     */
    public function incrementApiCalls(int $count = 1, bool $success = true, ?string $endpoint = null): void
    {
        $this->increment('api_calls', $count);

        if ($success) {
            $this->increment('api_calls_success', $count);
        } else {
            $this->increment('api_calls_failed', $count);
        }

        if ($endpoint) {
            $breakdown = $this->endpoint_breakdown ?? [];
            $breakdown[$endpoint] = ($breakdown[$endpoint] ?? 0) + $count;
            $this->update(['endpoint_breakdown' => $breakdown]);
        }
    }

    /**
     * Record an error.
     *
     * @param string $errorType
     * @param int $count
     */
    public function recordError(string $errorType, int $count = 1): void
    {
        $errors = $this->error_breakdown ?? [];
        $errors[$errorType] = ($errors[$errorType] ?? 0) + $count;
        $this->update(['error_breakdown' => $errors]);
    }
}
