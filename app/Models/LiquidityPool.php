<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiquidityPool extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'pool_id',
        'base_asset',
        'quote_asset',
        'base_balance',
        'quote_balance',
        'total_shares',
        'status',
        'total_liquidity',
        'volume_24h',
        'fee_percentage',
        'metadata'
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}