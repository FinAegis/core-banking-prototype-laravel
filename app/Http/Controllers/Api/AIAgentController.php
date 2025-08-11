<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\AI\Services\AIAgentService;
use App\Domain\AI\Services\ConversationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="AI Agent",
 *     description="AI Agent chat and conversation management"
 * )
 */
class AIAgentController extends Controller
{
    public function __construct(
        private readonly AIAgentService $aiAgentService,
        private readonly ConversationService $conversationService
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/ai/chat",
     *     operationId="aiChat",
     *     tags={"AI Agent"},
     *     summary="Send a message to the AI agent",
     *     description="Send a message to the AI agent and receive a response",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(property="message", type="string", example="What is my account balance?"),
     *             @OA\Property(property="conversation_id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
     *             @OA\Property(property="context", type="object", example={"account_id": "123"}),
     *             @OA\Property(property="model", type="string", enum={"gpt-4", "gpt-3.5-turbo", "claude-3"}, example="gpt-4"),
     *             @OA\Property(property="temperature", type="number", minimum=0, maximum=2, example=0.7),
     *             @OA\Property(property="stream", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="conversation_id", type="string"),
     *             @OA\Property(property="message_id", type="string"),
     *             @OA\Property(property="response", type="string"),
     *             @OA\Property(property="confidence", type="number"),
     *             @OA\Property(property="tools_used", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="context", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=429, description="Rate limit exceeded")
     * )
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message'         => 'required|string|max:4000',
            'conversation_id' => 'nullable|uuid',
            'context'         => 'nullable|array',
            'model'           => ['nullable', Rule::in(['gpt-4', 'gpt-3.5-turbo', 'claude-3'])],
            'temperature'     => 'nullable|numeric|min:0|max:2',
            'stream'          => 'nullable|boolean',
        ]);

        $conversationId = $validated['conversation_id'] ?? Str::uuid()->toString();
        $userId = (int) Auth::id();

        // Create or retrieve conversation
        $conversation = $this->conversationService->getOrCreate($conversationId, $userId);

        // Send message to AI agent
        $response = $this->aiAgentService->chat(
            message: $validated['message'],
            conversationId: $conversationId,
            userId: $userId,
            context: $validated['context'] ?? [],
            options: [
                'model'       => $validated['model'] ?? 'gpt-4',
                'temperature' => $validated['temperature'] ?? 0.7,
                'stream'      => $validated['stream'] ?? false,
            ]
        );

        return response()->json([
            'conversation_id' => $conversationId,
            'message_id'      => $response['message_id'],
            'response'        => $response['content'],
            'confidence'      => $response['confidence'] ?? null,
            'tools_used'      => $response['tools_used'] ?? [],
            'context'         => $response['context'] ?? [],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/ai/conversations",
     *     operationId="listConversations",
     *     tags={"AI Agent"},
     *     summary="List user conversations",
     *     description="Get a list of all conversations for the authenticated user",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of conversations to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, minimum=1, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of conversations",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="last_message", type="string"),
     *                 @OA\Property(property="message_count", type="integer"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function conversations(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $conversations = $this->conversationService->getUserConversations((int) Auth::id(), $limit);

        return response()->json($conversations);
    }

    /**
     * @OA\Get(
     *     path="/api/ai/conversations/{conversationId}",
     *     operationId="getConversation",
     *     tags={"AI Agent"},
     *     summary="Get conversation history",
     *     description="Retrieve the full message history for a conversation",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="conversationId",
     *         in="path",
     *         description="Conversation ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversation history",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string"),
     *             @OA\Property(property="messages", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="role", type="string", enum={"user", "assistant", "system"}),
     *                     @OA\Property(property="content", type="string"),
     *                     @OA\Property(property="timestamp", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="context", type="object"),
     *             @OA\Property(property="created_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Conversation not found")
     * )
     */
    public function getConversation(string $conversationId): JsonResponse
    {
        $conversation = $this->conversationService->getConversation($conversationId, (int) Auth::id());

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        return response()->json($conversation);
    }

    /**
     * @OA\Delete(
     *     path="/api/ai/conversations/{conversationId}",
     *     operationId="deleteConversation",
     *     tags={"AI Agent"},
     *     summary="Delete a conversation",
     *     description="Delete a conversation and all its messages",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="conversationId",
     *         in="path",
     *         description="Conversation ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Conversation deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Conversation not found")
     * )
     */
    public function deleteConversation(string $conversationId): JsonResponse
    {
        $deleted = $this->conversationService->deleteConversation($conversationId, (int) Auth::id());

        if (! $deleted) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/api/ai/feedback",
     *     operationId="submitFeedback",
     *     tags={"AI Agent"},
     *     summary="Submit feedback for an AI response",
     *     description="Submit user feedback about an AI agent response",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message_id", "rating"},
     *             @OA\Property(property="message_id", type="string"),
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="feedback", type="string", maxLength=1000)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Feedback submitted successfully"
     *     )
     * )
     */
    public function submitFeedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => 'required|string',
            'rating'     => 'required|integer|min:1|max:5',
            'feedback'   => 'nullable|string|max:1000',
        ]);

        // Store feedback for model improvement
        $this->aiAgentService->storeFeedback(
            messageId: $validated['message_id'],
            userId: (int) Auth::id(),
            rating: $validated['rating'],
            feedback: $validated['feedback'] ?? null
        );

        return response()->json(['message' => 'Feedback submitted successfully']);
    }
}
