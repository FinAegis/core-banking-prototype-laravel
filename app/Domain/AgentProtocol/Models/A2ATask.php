<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use App\Domain\AgentProtocol\Enums\A2ATaskState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * A2A Task model implementing the Agent-to-Agent protocol task lifecycle.
 *
 * @property string $id
 * @property string $sender_did
 * @property string $receiver_did
 * @property A2ATaskState $state
 * @property string|null $skill_id
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property array<string, mixed>|null $artifacts
 * @property array<string, mixed>|null $metadata
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class A2ATask extends Model
{
    use HasUuids;

    protected $table = 'a2a_tasks';

    protected $fillable = [
        'id',
        'sender_did',
        'receiver_did',
        'state',
        'skill_id',
        'input',
        'output',
        'artifacts',
        'metadata',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state'     => A2ATaskState::class,
            'input'     => 'array',
            'output'    => 'array',
            'artifacts' => 'array',
            'metadata'  => 'array',
        ];
    }

    /**
     * Scope: tasks sent by a specific DID.
     *
     * @param Builder<A2ATask> $query
     * @return Builder<A2ATask>
     */
    public function scopeForSender(Builder $query, string $did): Builder
    {
        return $query->where('sender_did', $did);
    }

    /**
     * Scope: tasks received by a specific DID.
     *
     * @param Builder<A2ATask> $query
     * @return Builder<A2ATask>
     */
    public function scopeForReceiver(Builder $query, string $did): Builder
    {
        return $query->where('receiver_did', $did);
    }

    /**
     * Scope: tasks that have not reached a terminal state.
     *
     * @param Builder<A2ATask> $query
     * @return Builder<A2ATask>
     */
    public function scopeActive(Builder $query): Builder
    {
        $terminalValues = array_map(
            fn (A2ATaskState $s): string => $s->value,
            array_filter(A2ATaskState::cases(), fn (A2ATaskState $s): bool => $s->isTerminal()),
        );

        return $query->whereNotIn('state', $terminalValues);
    }

    /**
     * Returns true when this task has reached a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->state->isTerminal();
    }

    /**
     * Transition the task to a new state, persisting the change.
     *
     * @throws RuntimeException when the transition is not allowed by the state machine.
     */
    public function transitionTo(A2ATaskState $newState): void
    {
        if (! $this->state->canTransitionTo($newState)) {
            throw new RuntimeException(
                sprintf(
                    'Cannot transition A2ATask from "%s" to "%s".',
                    $this->state->value,
                    $newState->value,
                ),
            );
        }

        $this->state = $newState;
        $this->save();
    }
}
