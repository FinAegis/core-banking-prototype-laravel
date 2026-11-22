<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deposit address of a card funding account, one per network and asset
 * (for Lepo: native USDC on Arbitrum or Polygon).
 *
 * @property string $id
 * @property string $funding_account_id
 * @property string $provider
 * @property string $network
 * @property string $asset
 * @property string $address
 * @property bool $is_active
 */
class CardDepositAddress extends Model
{
    use HasUuids;
    use UsesTenantConnection;

    protected $table = 'card_deposit_addresses';

    protected $fillable = [
        'funding_account_id',
        'provider',
        'network',
        'asset',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CardFundingAccount, $this>
     */
    public function fundingAccount(): BelongsTo
    {
        return $this->belongsTo(CardFundingAccount::class, 'funding_account_id');
    }
}
