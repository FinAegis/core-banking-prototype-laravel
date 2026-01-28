<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $transaction_id
 * @property string $status
 * @property float $risk_score
 * @property string $risk_level
 * @property array|null $patterns
 * @property array|null $triggered_rules
 * @property string|null $flag_reason
 * @property string|null $clear_reason
 * @property DateTimeInterface|null $analyzed_at
 * @property DateTimeInterface|null $flagged_at
 * @property DateTimeInterface|null $cleared_at
 * @property DateTimeInterface $created_at
 * @property DateTimeInterface $updated_at
 */
class TransactionMonitoring extends Model
{
    use UsesTenantConnection;

    protected $table = 'transaction_monitorings';

    protected $fillable = [
        'transaction_id',
        'status',
        'risk_score',
        'risk_level',
        'patterns',
        'triggered_rules',
        'flag_reason',
        'clear_reason',
        'analyzed_at',
        'flagged_at',
        'cleared_at',
    ];

    protected $casts = [
        'risk_score'      => 'float',
        'patterns'        => 'array',
        'triggered_rules' => 'array',
        'analyzed_at'     => 'datetime',
        'flagged_at'      => 'datetime',
        'cleared_at'      => 'datetime',
    ];

    /**
     * Get the transaction being monitored.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Account\Models\Transaction::class);
    }

    /**
     * Scope for high-risk transactions.
     */
    public function scopeHighRisk($query, float $minScore = 75.0)
    {
        return $query->where('risk_score', '>=', $minScore);
    }

    /**
     * Scope for flagged transactions.
     */
    public function scopeFlagged($query)
    {
        return $query->where('status', 'flagged');
    }

    /**
     * Scope for cleared transactions.
     */
    public function scopeCleared($query)
    {
        return $query->where('status', 'cleared');
    }

    /**
     * Scope for transactions by risk level.
     */
    public function scopeByRiskLevel($query, string $level)
    {
        return $query->where('risk_level', $level);
    }
}
