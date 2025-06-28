<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CgoPricingRound extends Model
{
    use HasFactory;

    protected $fillable = [
        'round_number',
        'share_price',
        'max_shares_available',
        'shares_sold',
        'total_raised',
        'started_at',
        'ended_at',
        'is_active',
    ];

    protected $casts = [
        'share_price' => 'decimal:4',
        'max_shares_available' => 'decimal:4',
        'shares_sold' => 'decimal:4',
        'total_raised' => 'decimal:2',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function investments(): HasMany
    {
        return $this->hasMany(CgoInvestment::class, 'round_id');
    }

    public function getRemainingSharesAttribute(): float
    {
        return $this->max_shares_available - $this->shares_sold;
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->max_shares_available == 0) {
            return 0;
        }
        return ($this->shares_sold / $this->max_shares_available) * 100;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getCurrentRound()
    {
        return self::active()->first();
    }

    public static function getNextSharePrice(): float
    {
        $lastRound = self::orderBy('round_number', 'desc')->first();
        if (!$lastRound) {
            return 10.00; // Starting price $10 per share
        }
        
        // Increase price by 10% each round
        return round($lastRound->share_price * 1.10, 4);
    }
}
