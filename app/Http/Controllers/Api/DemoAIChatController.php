<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\AI\Services\AgentOrchestratorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Demo AI Chat',
    description: 'Public demo AI chat endpoint (no authentication required)'
)]
class DemoAIChatController extends Controller
{
    public function __construct(
        private readonly AgentOrchestratorService $orchestrator,
    ) {
    }

        #[OA\Post(
            path: '/api/demo/ai-chat',
            operationId: 'demoAiChat',
            tags: ['Demo AI Chat'],
            summary: 'Send a message to the demo AI agent',
            description: 'Public endpoint for demo AI chat. No authentication required. Rate limited to 60 requests/minute.',
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['message'], properties: [
            new OA\Property(property: 'message', type: 'string', maxLength: 500, example: 'What is my account balance?'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'AI agent response',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message_id', type: 'string'),
        new OA\Property(property: 'content', type: 'string'),
        new OA\Property(property: 'confidence', type: 'number'),
        new OA\Property(property: 'tools_used', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'agents_used', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'metadata', type: 'object'),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 429,
        description: 'Rate limit exceeded'
    )]
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $response = $this->orchestrator->process($validated['message']);

        return response()->json($response);
    }
}
