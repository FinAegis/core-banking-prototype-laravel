<?php

namespace App\Domain\Governance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static static|null first(array $columns = ['*'])
 * @method static static create(array $attributes = [])
 */
class GcuVote extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'proposal_id',
        'user_uuid',
        'vote',
        'voting_power',
        'signature',
        'metadata',
    ];

    protected $casts = [
        'voting_power' => 'decimal:4',
        'metadata'     => 'array',
    ];

    /**
     * Get the proposal this vote belongs to.
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(GcuVotingProposal::class, 'proposal_id');
    }

    /**
     * Get the user who cast this vote.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Generate signature for vote verification.
     */
    public function generateSignature(): string
    {
        $data = [
            'proposal_id'  => $this->proposal_id,
            'user_uuid'    => $this->user_uuid,
            'vote'         => $this->vote,
            'voting_power' => $this->voting_power,
            'timestamp'    => $this->created_at?->timestamp,
        ];

        return hash('sha256', json_encode($data) . config('app.key'));
    }

    /**
     * Verify vote signature.
     */
    public function verifySignature(): bool
    {
        return $this->signature === $this->generateSignature();
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
