<?php

namespace App\Domain\Lending\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereDate(string $column, string|\DateTimeInterface $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereMonth(string $column, string|\DateTimeInterface $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereYear(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values)
 * @method static \Illuminate\Database\Eloquent\Builder whereBetween(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder whereNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder whereNotNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder latest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder oldest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder with(array|string $relations)
 * @method static \Illuminate\Database\Eloquent\Builder withCount(array|string $relations)
 * @method static \Illuminate\Database\Eloquent\Builder has(string $relation, string $operator = '>=', int $count = 1, string $boolean = 'and', \Closure $callback = null)
 * @method static \Illuminate\Database\Eloquent\Builder distinct(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder groupBy(string ...$groups)
 * @method static \Illuminate\Database\Eloquent\Builder limit(int $value)
 * @method static \Illuminate\Database\Eloquent\Builder take(int $value)
 * @method static \Illuminate\Database\Eloquent\Builder skip(int $value)
 * @method static \Illuminate\Database\Eloquent\Builder offset(int $value)
 * @method static \Illuminate\Database\Eloquent\Builder selectRaw(string $expression, array $bindings = [])
 * @method static \Illuminate\Database\Eloquent\Builder lockForUpdate()
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static static firstOrCreate(array $attributes, array $values = [])
 * @method static static firstOrNew(array $attributes, array $values = [])
 * @method static static create(array $attributes = [])
 * @method static static forceCreate(array $attributes)
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static firstOrFail(array $columns = ['*'])
 * @method static static findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static \Illuminate\Support\Collection pluck(string $column, string|null $key = null)
 * @method static int count(string $columns = '*')
 * @method static mixed sum(string $column)
 * @method static mixed avg(string $column)
 * @method static mixed max(string $column)
 * @method static mixed min(string $column)
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static bool delete()
 * @method static bool forceDelete()
 * @method static bool restore()
 * @method static bool update(array $attributes = [])
 * @method static int increment(string $column, float|int $amount = 1, array $extra = [])
 * @method static int decrement(string $column, float|int $amount = 1, array $extra = [])
 * @method static \Illuminate\Database\Eloquent\Builder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder query()
 */
class LoanCollateral extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'id',
        'loan_id',
        'type',
        'description',
        'estimated_value',
        'currency',
        'status',
        'verification_document_id',
        'verified_at',
        'verified_by',
        'released_at',
        'liquidated_at',
        'liquidation_value',
        'last_valuation_date',
        'valuation_history',
        'metadata',
    ];

    protected $casts = [
        'estimated_value'     => 'decimal:2',
        'liquidation_value'   => 'decimal:2',
        'verified_at'         => 'datetime',
        'released_at'         => 'datetime',
        'liquidated_at'       => 'datetime',
        'last_valuation_date' => 'datetime',
        'valuation_history'   => 'array',
        'metadata'            => 'array',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending_verification' => 'yellow',
            'verified'             => 'green',
            'rejected'             => 'red',
            'released'             => 'blue',
            'liquidated'           => 'gray',
            default                => 'gray'
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    /**
     * Get the activity logs for this model.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function logs()
    {
        return $this->morphMany(\App\Domain\Activity\Models\Activity::class, 'subject');
    }
}
