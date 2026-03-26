<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Services\A2ATaskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Controller for the A2A Task lifecycle endpoints.
 *
 * Implements send, get, cancel, and list operations for A2A tasks
 * per the Agent-to-Agent protocol specification.
 */
class A2ATaskController extends Controller
{
    public function __construct(
        private readonly A2ATaskService $taskService,
    ) {
    }

    /**
     * Create (send) a new A2A task.
     *
     * @param Request $request The incoming HTTP request.
     * @return JsonResponse 201 with task data on success, 422 on validation failure.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sender_did' => 'required|string|max:255',
            'skill_id'   => 'required|string|max:128',
            'input'      => 'nullable|array',
            'metadata'   => 'nullable|array',
        ]);

        $task = $this->taskService->createTask(
            senderDid: $validated['sender_did'],
            skillId: $validated['skill_id'],
            input: $validated['input'] ?? null,
            metadata: $validated['metadata'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $task->id,
                'sender_did'   => $task->sender_did,
                'receiver_did' => $task->receiver_did,
                'state'        => $task->state->value,
                'skill_id'     => $task->skill_id,
                'input'        => $task->input,
                'metadata'     => $task->metadata,
                'created_at'   => $task->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Retrieve a task by its ID.
     *
     * @param string $taskId The UUID of the task to retrieve.
     * @return JsonResponse 200 with task data, or 404 if not found.
     */
    public function get(string $taskId): JsonResponse
    {
        $task = $this->taskService->getTask($taskId);

        if ($task === null) {
            return response()->json([
                'success' => false,
                'error'   => 'Task not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'            => $task->id,
                'sender_did'    => $task->sender_did,
                'receiver_did'  => $task->receiver_did,
                'state'         => $task->state->value,
                'skill_id'      => $task->skill_id,
                'input'         => $task->input,
                'output'        => $task->output,
                'artifacts'     => $task->artifacts,
                'metadata'      => $task->metadata,
                'error_message' => $task->error_message,
                'created_at'    => $task->created_at?->toIso8601String(),
                'updated_at'    => $task->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Cancel a task that has not yet reached a terminal state.
     *
     * @param string $taskId The UUID of the task to cancel.
     * @return JsonResponse 200 on success, 404 if not found, 422 if already terminal.
     */
    public function cancel(string $taskId): JsonResponse
    {
        try {
            $task = $this->taskService->cancelTask($taskId);

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'         => $task->id,
                    'state'      => $task->state->value,
                    'updated_at' => $task->updated_at?->toIso8601String(),
                ],
            ]);
        } catch (RuntimeException $e) {
            Log::warning('A2A task cancel rejected', [
                'task_id' => $taskId,
                'reason'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List tasks for a given sender DID.
     *
     * @param Request $request The incoming HTTP request (requires sender_did query param).
     * @return JsonResponse 200 with task list, 422 if sender_did is missing.
     */
    public function list(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sender_did' => 'required|string|max:255',
            'state'      => 'nullable|string',
        ]);

        $tasks = $this->taskService->listTasksForSender(
            senderDid: $validated['sender_did'],
            state: $validated['state'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data'    => $tasks->map(fn ($task) => [
                'id'         => $task->id,
                'sender_did' => $task->sender_did,
                'state'      => $task->state->value,
                'skill_id'   => $task->skill_id,
                'created_at' => $task->created_at?->toIso8601String(),
                'updated_at' => $task->updated_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'sender_did' => $validated['sender_did'],
                'count'      => $tasks->count(),
            ],
        ]);
    }
}
