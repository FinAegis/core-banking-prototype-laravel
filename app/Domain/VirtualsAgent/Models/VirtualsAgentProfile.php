<?php

declare(strict_types=1);

namespace App\Domain\VirtualsAgent\Models;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\VirtualsAgent\Enums\AgentStatus;
use App\Domain\VisaCli\Models\VisaCliSpendingLimit;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Virtuals Protocol agent profile — links an on-chain Virtuals agent to
 * FinAegis spending limits, cards, and TrustCert credentials.
 *
 * @property string $id
 * @property string $virtuals_agent_id
 * @property int $employer_user_id
 * @property string $agent_name
 * @property string|null $agent_description
 * @property AgentStatus $status
 * @property string|null $x402_spending_limit_id
 * @property string|null $card_id
 * @property string|null $trustcert_subject_id
 * @property string $chain
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class VirtualsAgentProfile extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'virtuals_agent_profiles';

    protected $fillable = [
        'virtuals_agent_id',
        'employer_user_id',
        'agent_name',
        'agent_description',
        'status',
        'x402_spending_limit_id',
        'card_id',
        'trustcert_subject_id',
        'chain',
        'metadata',
    ];

    protected $casts = [
        'status'   => AgentStatus::class,
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => AgentStatus::REGISTERED,
        'chain'  => 'base',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    /**
     * @return BelongsTo<User, $this>
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_user_id');
    }

    /**
     * @return BelongsTo<VisaCliSpendingLimit, $this>
     */
    public function x402SpendingLimit(): BelongsTo
    {
        return $this->belongsTo(VisaCliSpendingLimit::class, 'x402_spending_limit_id');
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Determine whether the agent is active and can perform operations.
     */
    public function isActive(): bool
    {
        return $this->status === AgentStatus::ACTIVE;
    }

    /**
     * Determine whether the agent can spend the given amount.
     * Delegates to the linked spending limit when present.
     */
    public function canSpend(int $amountCents = 0): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->x402_spending_limit_id === null) {
            return false;
        }

        $limit = $this->x402SpendingLimit;

        if ($limit === null) {
            return false;
        }

        return $limit->canSpend($amountCents);
    }

    // ----------------------------------------------------------------
    // API Serialization
    // ----------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function toApiResponse(): array
    {
        return [
            'id'                  => $this->id,
            'virtualsAgentId'     => $this->virtuals_agent_id,
            'employerUserId'      => $this->employer_user_id,
            'agentName'           => $this->agent_name,
            'agentDescription'    => $this->agent_description,
            'status'              => $this->status->value,
            'x402SpendingLimitId' => $this->x402_spending_limit_id,
            'cardId'              => $this->card_id,
            'trustcertSubjectId'  => $this->trustcert_subject_id,
            'chain'               => $this->chain,
            'metadata'            => $this->metadata,
            'createdAt'           => $this->created_at->toIso8601String(),
            'updatedAt'           => $this->updated_at->toIso8601String(),
        ];
    }
}
