<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Trial-card fingerprint used by Backend-Q5 trial-abuse gate.
 *
 * @property string                         $fingerprint_hash
 * @property int|null                       $first_user_id
 * @property int|null                       $last_user_id
 * @property \Illuminate\Support\Carbon     $first_used_at
 * @property \Illuminate\Support\Carbon     $last_used_at
 * @property int                            $trial_user_count
 * @property string|null                    $stripe_payment_method_id
 */
final class TrialCardFingerprint extends Model
{
    protected $table = 'trial_card_fingerprints';

    protected $primaryKey = 'fingerprint_hash';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'fingerprint_hash',
        'first_user_id',
        'last_user_id',
        'first_used_at',
        'last_used_at',
        'trial_user_count',
        'stripe_payment_method_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_used_at'    => 'datetime',
            'last_used_at'     => 'datetime',
            'trial_user_count' => 'integer',
            'first_user_id'    => 'integer',
            'last_user_id'     => 'integer',
        ];
    }
}
