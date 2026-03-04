<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $provider
 * @property string $type
 * @property string $fiat_currency
 * @property float|null $fiat_amount
 * @property string $crypto_currency
 * @property float|null $crypto_amount
 * @property string $status
 * @property string|null $provider_session_id
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class RampSession extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'provider',
        'type',
        'fiat_currency',
        'fiat_amount',
        'crypto_currency',
        'crypto_amount',
        'status',
        'provider_session_id',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fiat_amount'   => 'float',
            'crypto_amount' => 'float',
            'metadata'      => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
