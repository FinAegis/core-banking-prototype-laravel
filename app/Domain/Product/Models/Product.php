<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use UsesTenantConnection;

    protected $table = 'products';

    protected $fillable = [
        'id',
        'name',
        'description',
        'category',
        'type',
        'status',
        'features',
        'prices',
        'metadata',
        'popularity_score',
        'activated_at',
        'deactivated_at',
    ];

    protected $casts = [
        'features'         => 'array',
        'prices'           => 'array',
        'metadata'         => 'array',
        'popularity_score' => 'integer',
        'activated_at'     => 'datetime',
        'deactivated_at'   => 'datetime',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function userProducts(): HasMany
    {
        return $this->hasMany(UserProduct::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getPrice(string $currency = 'USD'): ?array
    {
        if (! is_array($this->prices)) {
            return null;
        }

        foreach ($this->prices as $price) {
            if ($price['currency'] === $currency) {
                return $price;
            }
        }

        return null;
    }

    public function hasFeature(string $featureCode): bool
    {
        if (! is_array($this->features)) {
            return false;
        }

        foreach ($this->features as $feature) {
            if ($feature['code'] === $featureCode && ($feature['enabled'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    public function getFeature(string $featureCode): ?array
    {
        if (! is_array($this->features)) {
            return null;
        }

        foreach ($this->features as $feature) {
            if ($feature['code'] === $featureCode) {
                return $feature;
            }
        }

        return null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopePopular($query, int $limit = 10)
    {
        return $query->orderBy('popularity_score', 'desc')->limit($limit);
    }
}
