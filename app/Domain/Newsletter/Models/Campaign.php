<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $uuid
 * @property string $name
 * @property string $subject
 * @property string $content
 * @property string $status
 * @property string|null $segment
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property int $recipients_count
 * @property int $delivered_count
 * @property int $opened_count
 * @property int $clicked_count
 * @property int $bounced_count
 */
class Campaign extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'newsletter_campaigns';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'name',
        'subject',
        'content',
        'status',
        'segment',
        'scheduled_at',
        'sent_at',
        'recipients_count',
        'delivered_count',
        'opened_count',
        'clicked_count',
        'bounced_count',
    ];

    protected $casts = [
        'scheduled_at'     => 'datetime',
        'sent_at'          => 'datetime',
        'recipients_count' => 'integer',
        'delivered_count'  => 'integer',
        'opened_count'     => 'integer',
        'clicked_count'    => 'integer',
        'bounced_count'    => 'integer',
    ];

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * @return HasMany<Subscriber, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(Subscriber::class, 'source', 'segment');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeReadyToSend($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now());
    }
}
