<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PriceQuote Eloquent model — maps to the price_quotes table.
 *
 * This is the persistence aggregate. The wire-facing VO is
 * App\Domain\Pricing\ValueObjects\Quote (ADR-0003 wire-vs-internal asymmetry).
 *
 * price_quotes is on the default (global) connection — no UsesTenantConnection.
 * DB::transaction() is safe here without the multi-connection caveat.
 *
 * @property string $id                  RFC 4122 v4 UUID
 * @property int $user_id
 * @property string $user_tier           'free' | 'pro'
 * @property string $kind
 * @property array<string, mixed> $request_payload
 * @property array<string, mixed> $response_payload
 * @property string $entity_key          SHA256 of intent (Q2.1)
 * @property string|null $user_op_hash   0x + keccak256 hex; null for fiat kinds
 * @property array<string, mixed>|null $user_op_payload
 * @property string $signature           64-char hex HMAC
 * @property string|null $superseded_by  UUID of newer quote on refresh
 * @property bool $terms_changed
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $consumed_at
 * @property string|null $consumed_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @see docs/superpowers/specs/2026-05-10-slice-3-pricing-design.md §5.5
 */
final class PriceQuote extends Model
{
    protected $table = 'price_quotes';

    /** UUID primary key — not auto-incrementing. */
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'user_tier',
        'kind',
        'request_payload',
        'response_payload',
        'entity_key',
        'user_op_hash',
        'user_op_payload',
        'signature',
        'superseded_by',
        'terms_changed',
        'expires_at',
        'consumed_at',
        'consumed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload'  => 'array',
            'response_payload' => 'array',
            'user_op_payload'  => 'array',
            'terms_changed'    => 'boolean',
            'expires_at'       => 'datetime',
            'consumed_at'      => 'datetime',
        ];
    }

    /**
     * Whether this quote is currently usable (not expired and not consumed).
     */
    public function isLive(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture();
    }

    /**
     * Whether this quote has been consumed by a downstream endpoint.
     */
    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    /**
     * Human-readable status for GET responses.
     */
    public function statusLabel(): string
    {
        if ($this->superseded_by !== null) {
            return 'superseded';
        }

        if ($this->isConsumed()) {
            return 'consumed';
        }

        if ($this->expires_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}
