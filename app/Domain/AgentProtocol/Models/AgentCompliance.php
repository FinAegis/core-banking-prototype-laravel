<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCompliance extends Model
{
    use HasFactory;

    protected $table = 'agent_compliance';

    protected $fillable = [
        'compliance_id',
        'agent_id',
        'status',
        'level',
        'risk_score',
        'linked_customer_id',
        'linked_at',
        'link_metadata',
        'transaction_limits',
        'metadata',
    ];

    protected $casts = [
        'risk_score'         => 'integer',
        'link_metadata'      => 'array',
        'transaction_limits' => 'array',
        'metadata'           => 'array',
        'linked_at'          => 'datetime',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\AgentComplianceFactory::new();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
