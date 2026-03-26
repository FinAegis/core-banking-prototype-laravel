<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Enums\A2ATaskState;
use App\Domain\AgentProtocol\Models\A2ATask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service for managing the A2A Task lifecycle.
 *
 * Handles task creation, retrieval, cancellation, and listing
 * according to the Agent-to-Agent protocol specification.
 */
class A2ATaskService
{
    /**
     * Create a new A2A task with state SUBMITTED.
     *
     * @param string $senderDid The DID of the agent sending the task.
     * @param string $skillId The skill identifier to invoke on the receiver.
     * @param array<string, mixed>|null $input Optional task input payload.
     * @param array<string, mixed>|null $metadata Optional task metadata.
     * @return A2ATask The newly created task.
     */
    public function createTask(
        string $senderDid,
        string $skillId,
        ?array $input,
        ?array $metadata,
    ): A2ATask {
        $receiverDid = config('app.url');

        /** @var A2ATask $task */
        $task = A2ATask::create([
            'sender_did'   => $senderDid,
            'receiver_did' => (string) $receiverDid,
            'state'        => A2ATaskState::SUBMITTED,
            'skill_id'     => $skillId,
            'input'        => $input,
            'metadata'     => $metadata,
        ]);

        Log::info('A2A task created', [
            'task_id'      => $task->id,
            'sender_did'   => $senderDid,
            'skill_id'     => $skillId,
            'receiver_did' => $task->receiver_did,
        ]);

        return $task;
    }

    /**
     * Retrieve a task by its UUID.
     *
     * @param string $taskId The UUID of the task.
     * @return A2ATask|null The task or null if not found.
     */
    public function getTask(string $taskId): ?A2ATask
    {
        return A2ATask::find($taskId);
    }

    /**
     * Cancel a task that is not in a terminal state.
     *
     * @param string $taskId The UUID of the task to cancel.
     * @return A2ATask The updated task after cancellation.
     * @throws RuntimeException When the task is already in a terminal state.
     */
    public function cancelTask(string $taskId): A2ATask
    {
        /** @var A2ATask $task */
        $task = A2ATask::findOrFail($taskId);

        if ($task->isTerminal()) {
            throw new RuntimeException(
                sprintf(
                    'Cannot cancel task "%s": already in terminal state "%s".',
                    $task->id,
                    $task->state->value,
                ),
            );
        }

        $task->transitionTo(A2ATaskState::CANCELED);

        Log::info('A2A task canceled', [
            'task_id'    => $task->id,
            'sender_did' => $task->sender_did,
        ]);

        return $task;
    }

    /**
     * List tasks for a given sender DID, with optional state filter.
     *
     * @param string $senderDid The DID of the sender to filter by.
     * @param string|null $state Optional state value to filter results.
     * @return Collection<int, A2ATask> Collection of matching tasks (max 100).
     */
    public function listTasksForSender(string $senderDid, ?string $state): Collection
    {
        $query = A2ATask::forSender($senderDid);

        if ($state !== null) {
            $query->where('state', $state);
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
    }
}
