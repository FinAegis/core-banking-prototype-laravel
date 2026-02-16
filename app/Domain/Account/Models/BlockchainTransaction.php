<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $address_uuid
 * @property string $tx_hash
 * @property string $type
 * @property string $amount
 * @property string $fee
 * @property string $from_address
 * @property string $to_address
 * @property string $chain
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class BlockchainTransaction extends Model
{
    use HasUuids;

    protected $table = 'blockchain_address_transactions';

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
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<BlockchainAddress, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(BlockchainAddress::class, 'address_uuid', 'uuid');
    }
}
