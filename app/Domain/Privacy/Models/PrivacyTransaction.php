<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted privacy transaction calldata.
 *
 * Stores the EVM calldata returned by RAILGUN bridge operations so mobile
 * clients can retrieve it later if the initial HTTP response is lost.
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $tx_hash
 * @property string $operation
 * @property string $token
 * @property string $amount
 * @property string $network
 * @property string $to_address
 * @property string $calldata
 * @property string|null $value
 * @property string|null $gas_estimate
 * @property string $status
 * @property string|null $recipient
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PrivacyTransaction extends Model
{
    use HasUuids;

    protected $table = 'privacy_transactions';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'tx_hash',
        'operation',
        'token',
        'amount',
        'network',
        'to_address',
        'calldata',
        'value',
        'gas_estimate',
        'status',
        'recipient',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calldata' => 'encrypted',
            'metadata' => 'json',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for transactions owned by a user.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PrivacyTransaction> $query
     * @return \Illuminate\Database\Eloquent\Builder<PrivacyTransaction>
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for transactions on a specific network.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PrivacyTransaction> $query
     * @return \Illuminate\Database\Eloquent\Builder<PrivacyTransaction>
     */
    public function scopeForNetwork($query, string $network)
    {
        return $query->where('network', $network);
    }

    /**
     * Scope for transactions with a specific tx hash.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PrivacyTransaction> $query
     * @return \Illuminate\Database\Eloquent\Builder<PrivacyTransaction>
     */
    public function scopeForTxHash($query, string $txHash)
    {
        return $query->where('tx_hash', $txHash);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiResponse(): array
    {
        return [
            'id'           => $this->id,
            'tx_hash'      => $this->tx_hash,
            'operation'    => $this->operation,
            'token'        => $this->token,
            'amount'       => $this->amount,
            'network'      => $this->network,
            'to_address'   => $this->to_address,
            'calldata'     => $this->calldata,
            'value'        => $this->value,
            'gas_estimate' => $this->gas_estimate,
            'status'       => $this->status,
            'recipient'    => $this->recipient,
            'created_at'   => $this->created_at->toIso8601String(),
        ];
    }
}
