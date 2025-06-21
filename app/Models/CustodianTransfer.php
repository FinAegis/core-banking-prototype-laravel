<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustodianTransfer extends Model
{
    use HasFactory;
    
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'from_account_uuid',
        'to_account_uuid',
        'from_custodian_account_id',
        'to_custodian_account_id',
        'amount',
        'asset_code',
        'reference',
        'status',
        'transfer_type',
        'failure_reason',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];
}