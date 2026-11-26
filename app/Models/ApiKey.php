<?php

namespace App\Models;

use App\Support\DecimalString;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $transfer_limit
 * @property string|null $daily_limit
 * @property array<int, mixed>|null $allowed_account_uuids
 *
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static firstOrFail(array $columns = ['*'])
 * @method static int count(string $columns = '*')
 * @method static bool exists()
 * @method static static create(array $attributes = [])
 * @method static static updateOrCreate(array $attributes, array $values = [])
 */
class ApiKey extends Model
{
    use HasFactory;

    /**
     * Request attribute set once this request's use of the key is counted.
     */
    public const USAGE_RECORDED_ATTRIBUTE = 'api_key_usage_recorded';

    protected $fillable = [
        'uuid',
        'user_uuid',
        'name',
        'key_prefix',
        'key_hash',
        'description',
        'permissions',
        'rate_limits',
        'allowed_ips',
        'transfer_limit',
        'daily_limit',
        'allowed_account_uuids',
        'is_active',
        'last_used_at',
        'last_used_ip',
        'request_count',
        'expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'rate_limits' => 'array',
        'allowed_ips' => 'array',
        // Money limits in major units (null = the configured default) and
        // the accounts the key may move money from (null or empty = all of
        // the owner's accounts). See EnforceApiKeyMoneyLimits.
        'transfer_limit'        => 'decimal:2',
        'daily_limit'           => 'decimal:2',
        'allowed_account_uuids' => 'array',
        'is_active'             => 'boolean',
        'last_used_at'          => 'datetime',
        'expires_at'            => 'datetime',
        'request_count'         => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(
            function ($model) {
                if (empty($model->uuid)) {
                    $model->uuid = Str::uuid();
                }
            }
        );
    }

    /**
     * Get the user that owns the API key.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the logs for this API key.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ApiKeyLog::class);
    }

    /**
     * Generate a new API key.
     */
    public static function generateKey(): string
    {
        return 'fak_' . Str::random(32); // FinAegis API Key prefix
    }

    /**
     * Create a new API key for a user.
     */
    public static function createForUser(User $user, array $data): array
    {
        $plainKey = self::generateKey();
        $keyPrefix = substr($plainKey, 0, 8);
        $keyHash = Hash::make($plainKey);

        $apiKey = self::create(
            [
            'user_uuid'             => $user->uuid,
            'name'                  => $data['name'],
            'key_prefix'            => $keyPrefix,
            'key_hash'              => $keyHash,
            'description'           => $data['description'] ?? null,
            'permissions'           => $data['permissions'] ?? ['read'],
            'rate_limits'           => $data['rate_limits'] ?? null,
            'allowed_ips'           => $data['allowed_ips'] ?? null,
            'expires_at'            => $data['expires_at'] ?? null,
            'transfer_limit'        => $data['transfer_limit'] ?? null,
            'daily_limit'           => $data['daily_limit'] ?? null,
            'allowed_account_uuids' => $data['allowed_account_uuids'] ?? null,
            ]
        );

        return [
            'api_key'   => $apiKey,
            'plain_key' => $plainKey,
        ];
    }

    /**
     * Verify an API key.
     */
    public static function verify(string $plainKey): ?self
    {
        $keyPrefix = substr($plainKey, 0, 8);

        /** @var ApiKey|null $apiKey */
        $apiKey = self::where('key_prefix', $keyPrefix)
            ->where('is_active', true)
            ->where(
                function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                }
            )
            ->first();

        // Looking a key up is not a use of it: the auth middleware records
        // usage once the request is accepted.
        if ($apiKey && Hash::check($plainKey, $apiKey->key_hash)) {
            return $apiKey;
        }

        return null;
    }

    /**
     * Check if the API key has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        if (empty($this->permissions)) {
            return false;
        }

        return in_array($permission, $this->permissions) || in_array('*', $this->permissions);
    }

    /**
     * Check if the request IP is allowed.
     */
    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true; // No IP restrictions
        }

        return in_array($ip, $this->allowed_ips);
    }

    /**
     * Get rate limit for a specific endpoint.
     */
    public function getRateLimit(string $endpoint = 'default'): ?int
    {
        if (empty($this->rate_limits)) {
            return null; // Use default rate limits
        }

        return $this->rate_limits[$endpoint] ?? $this->rate_limits['default'] ?? null;
    }

    /**
     * The largest amount one money-moving request may carry, in major units
     * with two decimals: the key's own limit, or the configured default,
     * and never above the ceiling.
     *
     * @return numeric-string
     */
    public function effectiveTransferLimit(): string
    {
        return self::capped(DecimalString::fromNumeric($this->transfer_limit, 2) ?? self::defaultTransferLimit());
    }

    /**
     * The largest total this key may move per UTC day, in major units with
     * two decimals: the key's own limit, or the configured default, and
     * never above the ceiling.
     *
     * @return numeric-string
     */
    public function effectiveDailyLimit(): string
    {
        return self::capped(DecimalString::fromNumeric($this->daily_limit, 2) ?? self::defaultDailyLimit());
    }

    /**
     * @return numeric-string
     */
    public static function defaultTransferLimit(): string
    {
        return self::capped(self::configuredAmount('transfer_limit_default', '1000.00'));
    }

    /**
     * @return numeric-string
     */
    public static function defaultDailyLimit(): string
    {
        return self::capped(self::configuredAmount('daily_limit_default', '5000.00'));
    }

    /**
     * The highest limit any key may have, in major units with two decimals.
     *
     * @return numeric-string
     */
    public static function limitCeiling(): string
    {
        return self::configuredAmount('limit_max', '50000.00');
    }

    /**
     * The accounts this key may move money from. Empty means every account
     * its owner owns.
     *
     * @return list<string>
     */
    public function pinnedAccountUuids(): array
    {
        $uuids = $this->allowed_account_uuids;

        if (! is_array($uuids)) {
            return [];
        }

        return array_values(array_filter($uuids, fn ($uuid): bool => is_string($uuid) && $uuid !== ''));
    }

    public function isPinnedToAccounts(): bool
    {
        return $this->pinnedAccountUuids() !== [];
    }

    /**
     * Whether the pin allows this account. Ownership is checked separately.
     */
    public function pinAllowsAccount(string $accountUuid): bool
    {
        return ! $this->isPinnedToAccounts() || in_array($accountUuid, $this->pinnedAccountUuids(), true);
    }

    /**
     * The cache key of this key's money counter for the UTC day of $at,
     * in hundredths of a unit.
     */
    public function dailyMoneyCounterKey(?CarbonImmutable $at = null): string
    {
        $day = ($at ?? CarbonImmutable::now('UTC'))->setTimezone('UTC')->format('Y-m-d');

        return "api_key_money:daily:{$this->id}:{$day}";
    }

    /**
     * How much this key has moved (or is moving) today, in major units with
     * two decimals.
     *
     * @return numeric-string
     */
    public function movedToday(): string
    {
        $hundredths = (int) Cache::get($this->dailyMoneyCounterKey(), 0);

        return bcdiv((string) max(0, $hundredths), '100', 2);
    }

    /**
     * A limit for people to read, with thousands separators and no float
     * arithmetic: "50000.00" is "50,000.00".
     */
    public static function displayAmount(string $amount): string
    {
        $normalized = DecimalString::fromNumeric($amount, 2) ?? '0.00';
        $negative = str_starts_with($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($normalized, '-'), 2), 2, '00');
        $grouped = ltrim(strrev(chunk_split(strrev($whole), 3, ',')), ',');

        return ($negative ? '-' : '') . $grouped . '.' . str_pad($fraction, 2, '0');
    }

    /**
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    private static function capped(string $amount): string
    {
        $ceiling = self::limitCeiling();
        $amount = bcadd($amount, '0', 2);

        return bccomp($amount, $ceiling, 2) > 0 ? $ceiling : $amount;
    }

    /**
     * A positive amount from config('rate_limiting.api_key_money'), or the
     * built-in value when the setting is missing or not a positive number.
     *
     * @param  numeric-string  $fallback
     * @return numeric-string
     */
    private static function configuredAmount(string $setting, string $fallback): string
    {
        $value = DecimalString::fromNumeric(config("rate_limiting.api_key_money.{$setting}"), 2);

        if ($value === null || bccomp($value, '0', 2) <= 0) {
            return $fallback;
        }

        return $value;
    }

    /**
     * Record one accepted request. The count is raised in the database, so
     * concurrent requests never lose one.
     */
    public function recordUsage(string $ip): void
    {
        $now = now();

        static::query()->whereKey($this->getKey())->update([
            'last_used_at'  => $now,
            'last_used_ip'  => $ip,
            'request_count' => DB::raw('request_count + 1'),
        ]);

        $this->forceFill([
            'last_used_at'  => $now,
            'last_used_ip'  => $ip,
            'request_count' => (int) $this->request_count + 1,
        ])->syncOriginal();
    }

    /**
     * Record usage once per request, even when the request passes the key
     * check more than once (the v2 group checks read, a route can add write).
     */
    public function recordUsageOnce(Request $request): void
    {
        if ($request->attributes->get(self::USAGE_RECORDED_ATTRIBUTE) === $this->getKey()) {
            return;
        }

        $this->recordUsage((string) ($request->ip() ?? '127.0.0.1'));
        $request->attributes->set(self::USAGE_RECORDED_ATTRIBUTE, $this->getKey());
    }

    /**
     * Check if the API key is valid.
     */
    public function isValid(): bool
    {
        return $this->is_active &&
               (! $this->expires_at || $this->expires_at->isFuture());
    }

    /**
     * Revoke the API key.
     */
    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }
}
