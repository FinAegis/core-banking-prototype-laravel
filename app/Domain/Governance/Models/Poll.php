<?php

declare(strict_types=1);

namespace App\Domain\Governance\Models;

use App\Domain\Governance\Enums\PollStatus;
use App\Domain\Governance\Enums\PollType;
use App\Domain\Governance\ValueObjects\PollOption;
use App\Domain\Governance\ValueObjects\PollResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Database\Factories\PollFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Poll extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return PollFactory::new();
    }

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'type',
        'options',
        'start_date',
        'end_date',
        'status',
        'required_participation',
        'voting_power_strategy',
        'execution_workflow',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'uuid' => 'string',
        'type' => PollType::class,
        'status' => PollStatus::class,
        'options' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'required_participation' => 'integer',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($poll) {
            if (empty($poll->uuid)) {
                $poll->uuid = (string) Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function getOptionsAsObjects(): array
    {
        return array_map(
            fn (array $option) => new PollOption(
                id: $option['id'],
                label: $option['label'],
                description: $option['description'] ?? null,
                metadata: $option['metadata'] ?? []
            ),
            $this->options ?? []
        );
    }

    public function setOptionsFromObjects(array $options): void
    {
        $this->options = array_map(
            fn (PollOption $option) => $option->toArray(),
            $options
        );
    }

    public function isActive(): bool
    {
        return $this->status === PollStatus::ACTIVE
            && $this->start_date <= now()
            && $this->end_date >= now();
    }

    public function isExpired(): bool
    {
        return $this->end_date < now();
    }

    public function canVote(): bool
    {
        return $this->isActive() && !$this->isExpired();
    }

    public function getDurationInHours(): int
    {
        return (int) $this->start_date->diffInHours($this->end_date);
    }

    public function getTimeRemainingInHours(): int
    {
        if (!$this->isActive()) {
            return 0;
        }

        return (int) max(0, now()->diffInHours($this->end_date, false));
    }

    public function calculateResults(): PollResult
    {
        $votes = $this->votes()
            ->selectRaw('selected_options, voting_power')
            ->get()
            ->toArray();

        // Calculate total eligible voting power
        // For now, we'll use the sum of all votes as a baseline
        // This could be enhanced to calculate actual eligible users
        $totalEligibleVotingPower = (int) max(
            $this->votes()->sum('voting_power'),
            1 // Prevent division by zero
        );

        return PollResult::calculate(
            pollUuid: $this->uuid,
            votes: $votes,
            options: $this->options ?? [],
            totalEligibleVotingPower: $totalEligibleVotingPower
        );
    }

    public function hasUserVoted(string $userUuid): bool
    {
        return $this->votes()
            ->where('user_uuid', $userUuid)
            ->exists();
    }

    public function getUserVote(string $userUuid): ?Vote
    {
        return $this->votes()
            ->where('user_uuid', $userUuid)
            ->first();
    }

    public function getVoteCount(): int
    {
        return $this->votes()->count();
    }

    public function getTotalVotingPower(): int
    {
        return (int) $this->votes()->sum('voting_power');
    }

    public function scopeActive($query)
    {
        return $query->where('status', PollStatus::ACTIVE)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopeByType($query, PollType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCreator($query, string $userUuid)
    {
        return $query->where('created_by', $userUuid);
    }

    public function scopeExpired($query)
    {
        return $query->where('end_date', '<', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}