<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CgoInvestment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'round_id',
        'amount',
        'currency',
        'share_price',
        'shares_purchased',
        'ownership_percentage',
        'tier',
        'status',
        'payment_method',
        'crypto_address',
        'crypto_tx_hash',
        'certificate_number',
        'certificate_issued_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'share_price' => 'decimal:4',
        'shares_purchased' => 'decimal:4',
        'ownership_percentage' => 'decimal:6',
        'certificate_issued_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(CgoPricingRound::class, 'round_id');
    }

    public function getTierColorAttribute(): string
    {
        return match($this->tier) {
            'bronze' => 'yellow',
            'silver' => 'gray',
            'gold' => 'amber',
            default => 'gray',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'confirmed' => 'green',
            'cancelled' => 'red',
            'refunded' => 'gray',
            default => 'gray',
        };
    }

    public function generateCertificateNumber(): string
    {
        return 'CGO-' . strtoupper($this->tier[0]) . '-' . date('Y') . '-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }
}
