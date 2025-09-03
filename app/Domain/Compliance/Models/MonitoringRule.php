<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonitoringRule extends Model
{
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
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \Database\Factories\Domain\Compliance\MonitoringRuleFactory::new();
    }
}
