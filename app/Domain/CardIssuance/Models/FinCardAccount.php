<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's FinCard funding account (USD ledger mirror).
 *
 * @property string $id
 * @property string $user_id
 * @property string $fincard_account_id
 * @property string $currency
 * @property int $balance_cents
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class FinCardAccount extends Model
{
    use HasUuids;
    use UsesTenantConnection;

    protected $table = 'fincard_accounts';

    protected $fillable = [
        'user_id',
        'fincard_account_id',
        'currency',
        'balance_cents',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
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
