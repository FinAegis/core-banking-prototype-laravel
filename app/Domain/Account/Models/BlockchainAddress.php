<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $user_uuid
 * @property string $chain
 * @property string $address
 * @property string $public_key
 * @property string|null $derivation_path
 * @property string|null $label
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class BlockchainAddress extends Model
{
    use HasUuids;

    protected $table = 'blockchain_addresses';

    public $guarded = [];

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
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'metadata'  => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * @return HasMany<BlockchainTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BlockchainTransaction::class, 'address_uuid', 'uuid');
    }
}
