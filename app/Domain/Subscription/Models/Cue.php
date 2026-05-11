<?php

/**
 * Cue — per-user cue row for the mobile CueOrchestrator (Plan B Backend-Q8).
 *
 * Global table (no UsesTenantConnection). One row per user-kind-occurrence-window.
 * Idempotent writes via uniq_cues_idempotency. Dismissed via dismissed_at timestamp.
 * No SoftDeletes — dismissed cues are surfaced via dismissed_at; hard delete is
 * an admin-only escape hatch via the CueResource.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string             $id               UUID v4 primary key
 * @property int                $user_id          FK to users.id (BIGINT)
 * @property string             $kind             One of the CueKindRegistry keys
 * @property string             $priority         'critical' | 'high' | 'normal'
 * @property \Carbon\Carbon     $due_at           When the cue becomes available
 * @property \Carbon\Carbon     $expires_at       Absolute render window end
 * @property array<mixed>       $payload          Kind-specific data for mobile
 * @property \Carbon\Carbon|null $dismissed_at    NULL = pending
 * @property string|null        $dismissed_action 'cancelled' | 'kept' | 'dismissed'
 * @property string             $idempotency_key  sha256({user_id}:{kind}:{window})
 * @property \Carbon\Carbon     $created_at
 */
final class Cue extends Model
{
    protected $table = 'cues';

    /** UUID primary key — no auto-increment. */
    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Append-only except for dismissed_at / dismissed_action.
     * No updated_at — managed explicitly.
     */
    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'kind',
        'priority',
        'due_at',
        'expires_at',
        'payload',
        'dismissed_at',
        'dismissed_action',
        'idempotency_key',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at'       => 'datetime',
            'expires_at'   => 'datetime',
            'payload'      => 'array',
            'dismissed_at' => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    /** Auto-set UUID on creation. */
    protected static function booting(): void
    {
        static::creating(function (self $cue): void {
            if (empty($cue->id)) {
                $cue->id = (string) Str::uuid();
            }

            if (empty($cue->created_at)) {
                $cue->created_at = now();
            }
        });
    }

    /** Convenience: is this cue currently in the pending-and-visible window? */
    public function isPending(): bool
    {
        $now = now();

        return $this->dismissed_at === null
            && $this->due_at->lte($now)
            && $this->expires_at->gte($now);
    }

    /** Convenience: is this cue dismissed? */
    public function isDismissed(): bool
    {
        return $this->dismissed_at !== null;
    }

    /** Convenience: has the cue expired without dismissal? */
    public function isExpired(): bool
    {
        return $this->dismissed_at === null && $this->expires_at->lt(now());
    }
}
