<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Card pre-order waitlist entry.
 *
 * @property string $id
 * @property int $user_id
 * @property int $position
 * @property \Illuminate\Support\Carbon $joined_at
 * @property \Illuminate\Support\Carbon|null $notified_at
 * @property bool $converted
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CardWaitlist extends Model
{
    use HasUuids;

    protected $table = 'card_waitlist';

    protected $fillable = [
        'user_id',
        'position',
        'joined_at',
        'notified_at',
        'converted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at'   => 'datetime',
            'notified_at' => 'datetime',
            'converted'   => 'boolean',
            'position'    => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
