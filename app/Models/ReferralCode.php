<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $code
 * @property int $uses_count
 * @property int $max_uses
 * @property bool $active
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static Builder<static> usable()
 */
class ReferralCode extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'uses_count',
        'max_uses',
        'active',
        'expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'uses_count' => 'integer',
            'max_uses'   => 'integer',
            'active'     => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Referral, $this> */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('active', true)
            ->whereColumn('uses_count', '<', 'max_uses')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function canBeUsed(): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->uses_count >= $this->max_uses) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
