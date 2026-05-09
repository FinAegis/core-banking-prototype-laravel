<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Withdrawal-consent audit row written on every Stripe-Web subscription create.
 *
 * @property int                            $id
 * @property int                            $user_id
 * @property int|null                       $subscription_id
 * @property string                         $consent_text
 * @property int                            $consent_version
 * @property \Illuminate\Support\Carbon     $shown_at
 * @property \Illuminate\Support\Carbon     $accepted_at
 * @property string                         $ip_hash
 * @property string|null                    $user_agent
 */
final class SubscriptionConsentLog extends Model
{
    protected $table = 'subscription_consent_log';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'consent_text',
        'consent_version',
        'shown_at',
        'accepted_at',
        'ip_hash',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shown_at'        => 'datetime',
            'accepted_at'     => 'datetime',
            'consent_version' => 'integer',
        ];
    }
}
