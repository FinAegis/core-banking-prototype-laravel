<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Models;

use App\Domain\VisaCli\Enums\VisaCliCardStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property int $user_id
 * @property string $card_identifier
 * @property string $last4
 * @property string $network
 * @property string $status
 * @property string|null $github_username
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class VisaCliEnrolledCard extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'visa_cli_enrolled_cards';

    protected $fillable = [
        'user_id',
        'card_identifier',
        'last4',
        'network',
        'status',
        'github_username',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'status'   => VisaCliCardStatus::class,
    ];

    protected $attributes = [
        'status'  => VisaCliCardStatus::ENROLLED,
        'network' => 'visa',
    ];

    /**
     * @return BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [VisaCliCardStatus::ENROLLED, VisaCliCardStatus::ACTIVE]);
    }
}
