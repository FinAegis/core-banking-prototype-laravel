<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentReputation extends Model
{
    use HasFactory;

    protected $table = 'agent_reputations';

    protected $fillable = [
        'reputation_id',
        'agent_id',
        'score',
        'trust_level',
        'total_transactions',
        'successful_transactions',
        'failed_transactions',
        'disputed_transactions',
        'success_rate',
        'last_decay_at',
    ];

    protected $casts = [
        'score'                   => 'float',
        'total_transactions'      => 'integer',
        'successful_transactions' => 'integer',
        'failed_transactions'     => 'integer',
        'disputed_transactions'   => 'integer',
        'success_rate'            => 'float',
        'last_decay_at'           => 'datetime',
    ];
}
