<?php

declare(strict_types=1);

namespace App\Domain\RegTech\Models;

use App\Domain\RegTech\Enums\Jurisdiction;
use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $regulator
 * @property string $jurisdiction
 * @property string $endpoint_type
 * @property string $base_url
 * @property string|null $api_version
 * @property string|null $api_key_encrypted
 * @property string|null $api_secret_encrypted
 * @property array|null $headers
 * @property array|null $auth_config
 * @property bool $is_sandbox
 * @property bool $is_active
 * @property int $rate_limit_per_minute
 * @property int $timeout_seconds
 * @property \Carbon\Carbon|null $last_health_check
 * @property string|null $health_status
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder active()
 * @method static \Illuminate\Database\Eloquent\Builder production()
 * @method static \Illuminate\Database\Eloquent\Builder sandbox()
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static create(array $attributes = [])
 */
class RegulatoryEndpoint extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use UsesTenantConnection;

    public const TYPE_SUBMISSION = 'submission';

    public const TYPE_STATUS = 'status';

    public const TYPE_REFERENCE_DATA = 'reference_data';

    public const TYPE_VALIDATION = 'validation';

    public const HEALTH_HEALTHY = 'healthy';

    public const HEALTH_DEGRADED = 'degraded';

    public const HEALTH_UNHEALTHY = 'unhealthy';

    public const HEALTH_UNKNOWN = 'unknown';

    protected $fillable = [
        'uuid',
        'name',
        'regulator',
        'jurisdiction',
        'endpoint_type',
        'base_url',
        'api_version',
        'api_key_encrypted',
        'api_secret_encrypted',
        'headers',
        'auth_config',
        'is_sandbox',
        'is_active',
        'rate_limit_per_minute',
        'timeout_seconds',
        'last_health_check',
        'health_status',
        'metadata',
    ];

    protected $casts = [
        'headers'               => 'array',
        'auth_config'           => 'array',
        'metadata'              => 'array',
        'is_sandbox'            => 'boolean',
        'is_active'             => 'boolean',
        'rate_limit_per_minute' => 'integer',
        'timeout_seconds'       => 'integer',
        'last_health_check'     => 'datetime',
    ];

    protected $hidden = [
        'api_key_encrypted',
        'api_secret_encrypted',
    ];

    protected $attributes = [
        'is_active'             => true,
        'is_sandbox'            => true,
        'rate_limit_per_minute' => 60,
        'timeout_seconds'       => 30,
        'health_status'         => 'unknown',
        'endpoint_type'         => 'filing',
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get valid endpoint types.
     *
     * @return array<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_SUBMISSION,
            self::TYPE_STATUS,
            self::TYPE_REFERENCE_DATA,
            self::TYPE_VALIDATION,
        ];
    }

    /**
     * Scope to filter active endpoints.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter production endpoints.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProduction($query)
    {
        return $query->where('is_sandbox', false);
    }

    /**
     * Scope to filter sandbox endpoints.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSandbox($query)
    {
        return $query->where('is_sandbox', true);
    }

    /**
     * Scope to filter by jurisdiction.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $jurisdiction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeJurisdiction($query, string $jurisdiction)
    {
        return $query->where('jurisdiction', $jurisdiction);
    }

    /**
     * Get jurisdiction enum.
     */
    public function getJurisdictionEnum(): ?Jurisdiction
    {
        return Jurisdiction::tryFrom($this->jurisdiction);
    }

    /**
     * Get decrypted API key.
     */
    public function getApiKey(): ?string
    {
        if (! $this->api_key_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_key_encrypted);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Set API key (encrypts automatically).
     */
    public function setApiKey(string $apiKey): void
    {
        $this->api_key_encrypted = Crypt::encryptString($apiKey);
        $this->save();
    }

    /**
     * Get decrypted API secret.
     */
    public function getApiSecret(): ?string
    {
        if (! $this->api_secret_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_secret_encrypted);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Set API secret (encrypts automatically).
     */
    public function setApiSecret(string $apiSecret): void
    {
        $this->api_secret_encrypted = Crypt::encryptString($apiSecret);
        $this->save();
    }

    /**
     * Build full URL for a path.
     */
    public function buildUrl(string $path = ''): string
    {
        $baseUrl = rtrim($this->base_url, '/');
        $version = $this->api_version ? '/' . $this->api_version : '';
        $path = $path ? '/' . ltrim($path, '/') : '';

        return $baseUrl . $version . $path;
    }

    /**
     * Get request headers.
     *
     * @return array<string, string>
     */
    public function getRequestHeaders(): array
    {
        $headers = $this->headers ?? [];

        // Add API key if configured
        $apiKey = $this->getApiKey();
        if ($apiKey) {
            $authConfig = $this->auth_config ?? [];
            $headerName = $authConfig['api_key_header'] ?? 'Authorization';
            $headerPrefix = $authConfig['api_key_prefix'] ?? 'Bearer ';
            $headers[$headerName] = $headerPrefix . $apiKey;
        }

        // Default headers
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
        $headers['Accept'] = $headers['Accept'] ?? 'application/json';

        return $headers;
    }

    /**
     * Update health status.
     */
    public function updateHealthStatus(string $status, ?string $message = null): void
    {
        $this->update([
            'health_status'     => $status,
            'last_health_check' => now(),
            'metadata'          => array_merge($this->metadata ?? [], [
                'last_health_message' => $message,
            ]),
        ]);
    }

    /**
     * Check if endpoint is healthy.
     */
    public function isHealthy(): bool
    {
        return $this->health_status === self::HEALTH_HEALTHY;
    }
}
