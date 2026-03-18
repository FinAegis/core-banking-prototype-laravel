<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persistent card model for tracking issued cards.
 *
 * @property string $id
 * @property string $user_id
 * @property string $cardholder_id
 * @property string $issuer_card_token
 * @property string $issuer
 * @property string $last4
 * @property string $network
 * @property string $status
 * @property string $currency
 * @property string|null $label
 * @property string|null $funding_source
 * @property int|null $spend_limit_cents
 * @property string|null $spend_limit_interval
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $frozen_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Card extends Model
{
    use HasUuids;
    use UsesTenantConnection;

    protected $table = 'cards';

    protected $fillable = [
        'user_id',
        'cardholder_id',
        'issuer_card_token',
        'issuer',
        'last4',
        'network',
        'status',
        'currency',
        'label',
        'funding_source',
        'spend_limit_cents',
        'spend_limit_interval',
        'metadata',
        'expires_at',
        'frozen_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata'          => 'encrypted:array',
            'spend_limit_cents' => 'integer',
            'expires_at'        => 'datetime',
            'frozen_at'         => 'datetime',
            'cancelled_at'      => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isFrozen(): bool
    {
        return $this->status === 'frozen';
    }

    /**
     * @return BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * @return BelongsTo<Cardholder, $this>
     */
    public function cardholder(): BelongsTo
    {
        return $this->belongsTo(Cardholder::class);
    }

    public function getMaskedNumber(): string
    {
        return '**** **** **** ' . $this->last4;
    }
}
