<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The provider-side funding account of a cardholder: the balance its cards
 * are loaded from (Lepo's USD "Quantum" account). One per user and provider.
 *
 * @property string $id
 * @property int $user_id
 * @property string $cardholder_id
 * @property string $provider
 * @property string|null $provider_account_id
 * @property string $currency
 * @property string $status pending|active|suspended
 * @property int $balance_cents
 */
class CardFundingAccount extends Model
{
    use HasUuids;
    use UsesTenantConnection;

    protected $table = 'card_funding_accounts';

    protected $fillable = [
        'user_id',
        'cardholder_id',
        'provider',
        'provider_account_id',
        'currency',
        'status',
        'balance_cents',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency'      => 'USD',
        'status'        => 'pending',
        'balance_cents' => 0,
    ];

    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Cardholder, $this>
     */
    public function cardholder(): BelongsTo
    {
        return $this->belongsTo(Cardholder::class);
    }

    /**
     * @return HasMany<CardDepositAddress, $this>
     */
    public function depositAddresses(): HasMany
    {
        return $this->hasMany(CardDepositAddress::class, 'funding_account_id');
    }
}
