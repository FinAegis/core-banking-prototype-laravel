<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A card freeze that staff (StaffRoles::OPERATORS) or the card issuer
 * placed. While one exists, the card owner cannot lift the freeze; staff
 * can. A freeze the owner placed has no record, so the owner can lift it
 * again. Freezes placed before these records existed have none either, so
 * owners keep lifting their own earlier freezes.
 *
 * @property int $id
 * @property string $issuer_card_token
 * @property int|null $placed_by_user_id
 * @property string $source staff or issuer
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CardStaffFreeze extends Model
{
    use UsesTenantConnection;

    public const SOURCE_STAFF = 'staff';

    public const SOURCE_ISSUER = 'issuer';

    protected $table = 'card_staff_freezes';

    /**
     * @var list<string>
     */
    protected $fillable = [];

    public static function isHeld(string $cardToken): bool
    {
        return $cardToken !== '' && static::query()->where('issuer_card_token', $cardToken)->exists();
    }

    /**
     * Record a staff or issuer freeze. A later staff freeze replaces the
     * earlier record.
     */
    public static function place(string $cardToken, ?int $placedByUserId, string $source = self::SOURCE_STAFF): void
    {
        $hold = static::query()->where('issuer_card_token', $cardToken)->first() ?? new self();
        $hold->issuer_card_token = $cardToken;
        $hold->placed_by_user_id = $placedByUserId;
        $hold->source = $source;

        try {
            $hold->save();
        } catch (UniqueConstraintViolationException) {
            // A concurrent freeze recorded the same card first; the card is held either way.
        }
    }

    public static function lift(string $cardToken): void
    {
        static::query()->where('issuer_card_token', $cardToken)->delete();
    }
}
