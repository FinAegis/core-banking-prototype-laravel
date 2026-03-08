<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $image_url
 * @property string|null $action_url
 * @property string $action_type
 * @property string|null $cta_label
 * @property int $position
 * @property bool $active
 * @property \Carbon\Carbon|null $starts_at
 * @property \Carbon\Carbon|null $ends_at
 * @property array<string, mixed>|null $target_audience
 * @property array<int>|null $dismissed_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static Builder<static> active()
 * @method static Builder<static> currentlyVisible()
 * @method static Builder<static> notDismissedBy(int $userId)
 */
class Banner extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'image_url',
        'action_url',
        'action_type',
        'cta_label',
        'position',
        'active',
        'starts_at',
        'ends_at',
        'target_audience',
        'dismissed_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active'          => 'boolean',
            'position'        => 'integer',
            'starts_at'       => 'datetime',
            'ends_at'         => 'datetime',
            'target_audience' => 'array',
            'dismissed_by'    => 'array',
        ];
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        $now = now();

        return $query->active()
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeNotDismissedBy(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->whereNull('dismissed_by')
                ->orWhereJsonDoesntContain('dismissed_by', $userId);
        });
    }

    public function isDismissedBy(int $userId): bool
    {
        return in_array($userId, $this->dismissed_by ?? [], true);
    }

    public function dismiss(int $userId): void
    {
        $dismissed = $this->dismissed_by ?? [];
        if (! in_array($userId, $dismissed, true)) {
            $dismissed[] = $userId;
            $this->update(['dismissed_by' => $dismissed]);
        }
    }
}
