<?php

declare(strict_types=1);

namespace App\Domain\X402\Models;

use App\Domain\X402\DataObjects\MonetizedRouteConfig;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Monetized API endpoint — defines which routes require x402 payment.
 *
 * @property string $id
 * @property string $method
 * @property string $path
 * @property string $price
 * @property string $network
 * @property string $asset
 * @property string $scheme
 * @property string|null $description
 * @property string $mime_type
 * @property bool $is_active
 * @property array<string, mixed>|null $extra
 * @property int|null $team_id
 * @property int|null $created_by_user_id administrator who created the price (null: written before GHSA-385w, or by a seeder)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class X402MonetizedEndpoint extends Model
{
    use HasUuids;

    protected $table = 'x402_monetized_endpoints';

    /** Refusal when a route would get a second active price. */
    public const DUPLICATE_ROUTE_MESSAGE = 'This method and path already has an active price. Update that price, or deactivate it first.';

    protected $fillable = [
        'method',
        'path',
        'price',
        'network',
        'asset',
        'scheme',
        'description',
        'mime_type',
        'is_active',
        'extra',
        'team_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'extra'     => 'array',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    /**
     * @param Builder<X402MonetizedEndpoint> $query
     * @return Builder<X402MonetizedEndpoint>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Rows an administrator created first (rows without a creator were
     * written before prices became admin-only), then the oldest.
     *
     * @param Builder<X402MonetizedEndpoint> $query
     * @return Builder<X402MonetizedEndpoint>
     */
    public function scopeAdminCreatedFirst(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN created_by_user_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Whether an active row other than $exceptId prices this method and
     * path, whatever its team (the gate looks prices up by route only).
     */
    public static function activeRouteExists(string $method, string $path, ?string $exceptId = null): bool
    {
        return self::query()
            ->active()
            ->forRoute($method, $path)
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    /**
     * @param Builder<X402MonetizedEndpoint> $query
     * @return Builder<X402MonetizedEndpoint>
     */
    public function scopeForRoute(Builder $query, string $method, string $path): Builder
    {
        return $query->where('method', strtoupper($method))
            ->where('path', $path);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Check whether this endpoint configuration matches the given HTTP method and path.
     */
    public function matches(string $method, string $path): bool
    {
        return strtoupper($this->method) === strtoupper($method)
            && $this->path === $path;
    }

    /**
     * Convert this model into a MonetizedRouteConfig data object.
     */
    public function toMonetizedRouteConfig(): MonetizedRouteConfig
    {
        return new MonetizedRouteConfig(
            method: $this->method,
            path: $this->path,
            price: $this->price,
            network: $this->network,
            asset: $this->asset,
            scheme: $this->scheme,
            description: $this->description ?? '',
            mimeType: $this->mime_type,
            extra: $this->extra ?? [],
        );
    }

    // ----------------------------------------------------------------
    // API Serialization
    // ----------------------------------------------------------------

    /**
     * Format for API response.
     *
     * @return array<string, mixed>
     */
    public function toApiResponse(): array
    {
        return [
            'id'          => $this->id,
            'method'      => $this->method,
            'path'        => $this->path,
            'price'       => $this->price,
            'network'     => $this->network,
            'asset'       => $this->asset,
            'scheme'      => $this->scheme,
            'description' => $this->description,
            'mimeType'    => $this->mime_type,
            'isActive'    => $this->is_active,
            'extra'       => $this->extra,
            'teamId'      => $this->team_id,
            'createdAt'   => $this->created_at->toIso8601String(),
            'updatedAt'   => $this->updated_at->toIso8601String(),
        ];
    }
}
