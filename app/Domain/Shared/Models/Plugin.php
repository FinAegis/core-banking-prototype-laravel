<?php

declare(strict_types=1);

namespace App\Domain\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plugin extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'vendor',
        'name',
        'version',
        'display_name',
        'description',
        'author',
        'license',
        'homepage',
        'status',
        'permissions',
        'dependencies',
        'metadata',
        'path',
        'entry_point',
        'is_system',
        'installed_at',
        'activated_at',
        'last_updated_at',
    ];

    protected $casts = [
        'permissions'     => 'array',
        'dependencies'    => 'array',
        'metadata'        => 'array',
        'is_system'       => 'boolean',
        'installed_at'    => 'datetime',
        'activated_at'    => 'datetime',
        'last_updated_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isSystem(): bool
    {
        return $this->is_system;
    }

    public function getFullName(): string
    {
        return "{$this->vendor}/{$this->name}";
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByVendor($query, string $vendor)
    {
        return $query->where('vendor', $vendor);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<PluginReview, $this>
     */
    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PluginReview::class);
    }

    /**
     * Get the latest review.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<PluginReview, $this>
     */
    public function latestReview(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PluginReview::class)->latestOfMany('reviewed_at');
    }
}
