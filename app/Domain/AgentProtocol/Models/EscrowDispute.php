<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscrowDispute extends Model
{
    protected $table = 'escrow_disputes';

    protected $fillable = [
        'dispute_id',
        'escrow_id',
        'disputed_by',
        'reason',
        'evidence',
        'status',
        'resolution_method',
        'resolved_by',
        'resolved_at',
        'resolution_type',
        'resolution_allocation',
        'resolution_details',
    ];

    protected $casts = [
        'evidence'              => 'array',
        'resolution_allocation' => 'array',
        'resolution_details'    => 'array',
        'resolved_at'           => 'datetime',
    ];

    public function escrow(): BelongsTo
    {
        return $this->belongsTo(Escrow::class, 'escrow_id', 'escrow_id');
    }

    public function disputedByAgent(): BelongsTo
    {
        return $this->belongsTo(AgentIdentity::class, 'disputed_by', 'agent_id');
    }

    public function resolvedByAgent(): BelongsTo
    {
        return $this->belongsTo(AgentIdentity::class, 'resolved_by', 'agent_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isUnderInvestigation(): bool
    {
        return $this->status === 'investigating';
    }

    public function requiresArbitration(): bool
    {
        return $this->resolution_method === 'arbitration';
    }

    public function canBeResolvedAutomatically(): bool
    {
        return $this->resolution_method === 'automated';
    }

    public function getEvidenceSummary(): array
    {
        if (empty($this->evidence)) {
            return [];
        }

        return array_map(function ($item) {
            return [
                'type'         => $item['type'] ?? 'document',
                'description'  => $item['description'] ?? '',
                'submitted_at' => $item['submitted_at'] ?? null,
            ];
        }, $this->evidence);
    }
}
