<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's FinCard crypto deposit address for a given coin.
 *
 * @property string $id
 * @property string $user_id
 * @property string $fincard_account_id
 * @property string $coin_key
 * @property string|null $chain
 * @property string $address
 * @property int|null $min_deposit_cents
 * @property int|null $confirmations
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class FinCardDepositAddress extends Model
{
    use HasUuids;
    use UsesTenantConnection;

    protected $table = 'fincard_deposit_addresses';

    protected $fillable = [
        'user_id',
        'fincard_account_id',
        'coin_key',
        'chain',
        'address',
        'min_deposit_cents',
        'confirmations',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_deposit_cents' => 'integer',
            'confirmations'     => 'integer',
        ];
    }

    /**
     * @return BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
