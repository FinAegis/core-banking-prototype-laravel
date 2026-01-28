<?php

namespace App\Domain\Governance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
class GcuVotingProposal extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'proposed_composition',
        'current_composition',
        'rationale',
        'status',
        'voting_starts_at',
        'voting_ends_at',
        'minimum_participation',
        'minimum_approval',
        'total_gcu_supply',
        'total_votes_cast',
        'votes_for',
        'votes_against',
        'created_by',
        'implemented_at',
        'implementation_details',
    ];

    protected $casts = [
        'proposed_composition'   => 'array',
        'current_composition'    => 'array',
        'implementation_details' => 'array',
        'voting_starts_at'       => 'datetime',
        'voting_ends_at'         => 'datetime',
        'implemented_at'         => 'datetime',
        'minimum_participation'  => 'decimal:2',
        'minimum_approval'       => 'decimal:2',
        'total_gcu_supply'       => 'decimal:4',
        'total_votes_cast'       => 'decimal:4',
        'votes_for'              => 'decimal:4',
        'votes_against'          => 'decimal:4',
    ];

    /**
     * Get the creator of the proposal.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all votes for this proposal.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(GcuVote::class, 'proposal_id');
    }

    /**
     * Check if voting is currently active.
     */
    public function isVotingActive(): bool
    {
        return $this->status === 'active'
            && now()->between($this->voting_starts_at, $this->voting_ends_at);
    }

    /**
     * Check if the proposal has passed.
     */
    public function hasPassed(): bool
    {
        if ($this->status !== 'closed') {
            return false;
        }

        $participationRate = ($this->total_votes_cast / $this->total_gcu_supply) * 100;
        if ($participationRate < $this->minimum_participation) {
            return false;
        }

        $approvalRate = ($this->votes_for / $this->total_votes_cast) * 100;

        return $approvalRate >= $this->minimum_approval;
    }

    /**
     * Get the participation rate.
     */
    public function getParticipationRateAttribute(): float
    {
        if (! $this->total_gcu_supply || $this->total_gcu_supply == 0) {
            return 0;
        }

        return ($this->total_votes_cast / $this->total_gcu_supply) * 100;
    }

    /**
     * Get the approval rate.
     */
    public function getApprovalRateAttribute(): float
    {
        if ($this->total_votes_cast == 0) {
            return 0;
        }

        return ($this->votes_for / $this->total_votes_cast) * 100;
    }

    /**
     * Get time remaining for voting.
     */
    public function getTimeRemainingAttribute(): ?string
    {
        if (! $this->isVotingActive()) {
            return null;
        }

        return now()->diffForHumans(
            $this->voting_ends_at,
            [
                'parts' => 2,
                'short' => true,
            ]
        );
    }

    /**
     * Scope for active proposals.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('voting_starts_at', '<=', now())
            ->where('voting_ends_at', '>', now());
    }

    /**
     * Scope for upcoming proposals.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'active')
            ->where('voting_starts_at', '>', now());
    }

    /**
     * Scope for past proposals.
     */
    public function scopePast($query)
    {
        return $query->whereIn('status', ['closed', 'implemented', 'rejected']);
    }

    public function load($relations)
    {
        return $this->loadMissing($relations);
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
