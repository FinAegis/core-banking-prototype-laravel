<?php

namespace App\Domain\Account\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

/**
 * @property int $id
 * @property string|null $aggregate_uuid
 * @property int|null $aggregate_version
 * @property int $event_version
 * @property string $event_class
 * @property array $event_properties
 * @property array $meta_data
 * @property \Illuminate\Support\Carbon $created_at
 * @property string|null $compliance_status
 * @property string|null $risk_level
 * @property float|null $risk_score
 * @property array|null $patterns_detected
 * @property \Illuminate\Support\Carbon|null $flagged_at
 * @property string|null $flagged_by
 * @property string|null $flag_reason
 * @property \Illuminate\Support\Carbon|null $cleared_at
 * @property string|null $cleared_by
 * @property string|null $clear_reason
 */
class Transaction extends EloquentStoredEvent
{
    use HasFactory;

    public $table = 'transactions';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    public $casts = [
        'event_properties'  => 'array',
        'meta_data'         => 'array',
        'patterns_detected' => 'array',
        'risk_score'        => 'float',
        'flagged_at'        => 'datetime',
        'cleared_at'        => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'compliance_status',
        'risk_level',
        'risk_score',
        'patterns_detected',
        'flagged_at',
        'flagged_by',
        'flag_reason',
        'cleared_at',
        'cleared_by',
        'clear_reason',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\TransactionFactory
     */
    protected static function newFactory(): \Database\Factories\TransactionFactory
    {
        return \Database\Factories\TransactionFactory::new();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'aggregate_uuid', 'uuid');
    }
}
