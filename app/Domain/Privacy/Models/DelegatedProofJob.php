<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Delegated Proof Job model for server-side ZK proof generation.
 *
 * Tracks proof generation requests from low-end mobile devices that
 * cannot perform client-side proof generation due to resource constraints.
 *
 * @property string $id
 * @property string $user_id
 * @property string $proof_type
 * @property string $network
 * @property array<string, mixed> $public_inputs
 * @property string $encrypted_private_inputs
 * @property string $status
 * @property int $progress
 * @property string|null $proof
 * @property string|null $error
 * @property int|null $estimated_seconds
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DelegatedProofJob extends Model
{
    use HasUuids;

    protected $table = 'delegated_proof_jobs';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROVING = 'proving';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'proof_type',
        'network',
        'public_inputs',
        'encrypted_private_inputs',
        'status',
        'progress',
        'proof',
        'error',
        'estimated_seconds',
    ];

    protected $casts = [
        'public_inputs'     => 'array',
        'progress'          => 'integer',
        'estimated_seconds' => 'integer',
    ];

    /**
     * Get the user that owns this proof job.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the job is still in progress.
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROVING], true);
    }

    /**
     * Check if the job has completed successfully.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the job has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Update progress percentage.
     */
    public function updateProgress(int $progress): void
    {
        $this->update([
            'progress' => min(100, max(0, $progress)),
            'status'   => $progress > 0 ? self::STATUS_PROVING : $this->status,
        ]);
    }

    /**
     * Mark the job as completed with the generated proof.
     */
    public function markCompleted(string $proof): void
    {
        $this->update([
            'status'   => self::STATUS_COMPLETED,
            'progress' => 100,
            'proof'    => $proof,
        ]);
    }

    /**
     * Mark the job as failed with an error message.
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error'  => $error,
        ]);
    }

    /**
     * Scope for jobs owned by a user.
     *
     * @param \Illuminate\Database\Eloquent\Builder<DelegatedProofJob> $query
     * @return \Illuminate\Database\Eloquent\Builder<DelegatedProofJob>
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for jobs with a specific status.
     *
     * @param \Illuminate\Database\Eloquent\Builder<DelegatedProofJob> $query
     * @return \Illuminate\Database\Eloquent\Builder<DelegatedProofJob>
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Format the model for API responses.
     *
     * @return array<string, mixed>
     */
    public function toApiResponse(): array
    {
        $response = [
            'id'                => $this->id,
            'proof_type'        => $this->proof_type,
            'network'           => $this->network,
            'status'            => $this->status,
            'progress'          => $this->progress,
            'estimated_seconds' => $this->estimated_seconds,
            'created_at'        => $this->created_at->toIso8601String(),
        ];

        if ($this->isCompleted()) {
            $response['proof'] = $this->proof;
        }

        if ($this->isFailed()) {
            $response['error'] = $this->error;
        }

        return $response;
    }
}
