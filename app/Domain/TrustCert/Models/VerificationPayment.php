<?php

declare(strict_types=1);

namespace App\Domain\TrustCert\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationPayment extends Model
{
    use HasUuids;

    protected $table = 'verification_payments';

    protected $fillable = [
        'user_id',
        'application_id',
        'method',
        'amount',
        'currency',
        'status',
        'stripe_session_id',
        'iap_transaction_id',
        'platform',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<\App\Models\User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
