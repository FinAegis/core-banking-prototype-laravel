<?php

namespace App\Domain\Banking\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_uuid
 * @property string $bank_code
 * @property string $external_id
 * @property string $account_number
 * @property string $iban
 * @property string|null $swift
 * @property string $currency
 * @property string $account_type
 * @property string $status
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereYear(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereMonth(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereDate(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values)
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static \Illuminate\Support\Collection pluck(string $column, string|null $key = null)
 * @method static \Illuminate\Database\Eloquent\Builder selectRaw(string $expression, array $bindings = [])
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder latest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder oldest(string $column = null)
 * @method static mixed sum(string $column)
 * @method static int count(string $columns = '*')
 * @method static static|null first()
 * @method static \Illuminate\Database\Eloquent\Collection get(array|string $columns = ['*'])
 */
class BankAccountModel extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $table = 'bank_accounts';

    /**
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\BankAccountModelFactory
     */
    protected static function newFactory(): \Database\Factories\BankAccountModelFactory
    {
        return \Database\Factories\BankAccountModelFactory::new();
    }

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_uuid',
        'bank_code',
        'external_id',
        'account_number',
        'iban',
        'swift',
        'currency',
        'account_type',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the bank account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Scope to get active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get masked account number for display.
     */
    public function getMaskedAccountNumber(): string
    {
        $decrypted = decrypt($this->account_number);

        return '...' . substr($decrypted, -4);
    }

    /**
     * Get masked IBAN for display.
     */
    public function getMaskedIBAN(): string
    {
        $decrypted = decrypt($this->iban);

        return substr($decrypted, 0, 4) . ' **** **** **** ' . substr($decrypted, -4);
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
