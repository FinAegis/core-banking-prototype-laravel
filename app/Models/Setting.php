<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
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
 * @method static mixed get(string $key, mixed $default = null)
 */
class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'label',
        'description',
        'is_public',
        'is_encrypted',
        'validation_rules',
        'metadata',
    ];

    protected $casts = [
        'value'            => 'json',
        'validation_rules' => 'json',
        'metadata'         => 'json',
        'is_public'        => 'boolean',
        'is_encrypted'     => 'boolean',
    ];

    /**
     * Temporary property to store old value for audit logging.
     */
    public $oldValue;

    protected static function booted(): void
    {
        static::saving(
            function ($setting) {
                if (! $setting->exists) {
                    return;
                }

                $original = $setting->getOriginal();
                if ($original['value'] !== $setting->attributes['value']) {
                    $setting->oldValue = json_decode($original['value'], true);
                }
            }
        );

        static::saved(
            function ($setting) {
                Cache::forget("setting.{$setting->key}");
                Cache::forget('settings.all');
                Cache::forget("settings.group.{$setting->group}");

                // Create audit log if value changed
                if (isset($setting->oldValue)) {
                    SettingAudit::create(
                        [
                        'setting_id' => $setting->id,
                        'key'        => $setting->key,
                        'old_value'  => $setting->oldValue,
                        'new_value'  => $setting->value,
                        'changed_by' => auth()->user()->email ?? request()->ip() ?? 'system',
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'metadata'   => [
                        'user_id'    => auth()->id(),
                        'request_id' => request()->header('X-Request-ID'),
                        ],
                        ]
                    );
                }
            }
        );

        static::deleted(
            function ($setting) {
                Cache::forget("setting.{$setting->key}");
                Cache::forget('settings.all');
                Cache::forget("settings.group.{$setting->group}");
            }
        );
    }

    public function setValueAttribute($value): void
    {
        if ($this->is_encrypted && ! is_null($value)) {
            $encrypted = Crypt::encryptString(json_encode($value));
            $this->attributes['value'] = json_encode($encrypted);
        } else {
            $this->attributes['value'] = json_encode($value);
        }
    }

    public function getValueAttribute($value)
    {
        $decoded = json_decode($value, true);

        if ($this->is_encrypted && ! is_null($decoded)) {
            try {
                $decrypted = Crypt::decryptString($decoded);

                return json_decode($decrypted, true);
            } catch (Exception $e) {
                return $decoded;
            }
        }

        return $this->castValue($decoded);
    }

    protected function castValue($value)
    {
        return match ($this->type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'float'   => (float) $value,
            'array'   => (array) $value,
            'json'    => $value,
            default   => (string) $value,
        };
    }

    public static function get(string $key, $default = null)
    {
        return Cache::remember(
            "setting.{$key}",
            3600,
            function () use ($key, $default) {
                $setting = static::where('key', $key)->first();

                return $setting ? $setting->value : $default;
            }
        );
    }

    public static function set(string $key, $value, array $attributes = []): self
    {
        $setting = static::firstOrNew(['key' => $key]);

        foreach ($attributes as $attr => $val) {
            $setting->$attr = $val;
        }

        $setting->value = $value;
        $setting->save();

        return $setting;
    }

    public static function getGroup(string $group): array
    {
        return Cache::remember(
            "settings.group.{$group}",
            3600,
            function () use ($group) {
                return static::where('group', $group)
                    ->pluck('value', 'key')
                    ->toArray();
            }
        );
    }

    public static function getAllGrouped(): array
    {
        return Cache::remember(
            'settings.all.grouped',
            3600,
            function () {
                return static::all()
                    ->groupBy('group')
                    ->map(
                        function ($items) {
                            return $items->pluck('value', 'key');
                        }
                    )
                ->toArray();
            }
        );
    }

    public function audits(): HasMany
    {
        return $this->hasMany(SettingAudit::class);
    }

    /**
     * Get the activity logs for this model.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function logs()
    {
        return $this->morphMany(\App\Domain\Activity\Models\Activity::class, 'subject');
    }
}
