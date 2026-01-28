<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string|null $rule_type
 * @property array $conditions
 * @property array|null $actions
 * @property array|null $metadata
 * @property array|null $tags
 * @property float|null $threshold
 * @property string $severity
 * @property string|null $description
 * @property bool $is_active
 * @property bool $enabled
 * @property int $priority
 * @property float|null $effectiveness_score
 * @property float|null $false_positive_rate
 * @property int $trigger_count
 * @property int $true_positives
 * @property int $false_positives
 * @property DateTimeInterface|null $last_triggered_at
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class MonitoringRule extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'rule_type',
        'conditions',
        'actions',
        'metadata',
        'tags',
        'threshold',
        'severity',
        'description',
        'is_active',
        'enabled',
        'priority',
        'effectiveness_score',
        'false_positive_rate',
        'trigger_count',
        'true_positives',
        'false_positives',
        'last_triggered_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'conditions'          => 'array',
        'actions'             => 'array',
        'metadata'            => 'array',
        'tags'                => 'array',
        'threshold'           => 'float',
        'is_active'           => 'boolean',
        'enabled'             => 'boolean',
        'priority'            => 'integer',
        'effectiveness_score' => 'float',
        'false_positive_rate' => 'float',
        'trigger_count'       => 'integer',
        'true_positives'      => 'integer',
        'false_positives'     => 'integer',
        'last_triggered_at'   => 'datetime',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    protected $attributes = [
        'is_active'           => true,
        'enabled'             => true,
        'priority'            => 50,
        'effectiveness_score' => 50.0,
        'false_positive_rate' => 0.0,
        'trigger_count'       => 0,
        'true_positives'      => 0,
        'false_positives'     => 0,
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \Database\Factories\Domain\Compliance\MonitoringRuleFactory::new();
    }
}
