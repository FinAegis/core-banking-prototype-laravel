<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\Contracts\MCPServerInterface;
use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\Exceptions\MCPException;
use App\Domain\AI\Exceptions\ToolNotFoundException;
use App\Domain\AI\ValueObjects\MCPRequest;
use App\Domain\AI\ValueObjects\MCPResponse;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Shared\CQRS\CommandBus;
use App\Domain\Shared\Events\DomainEventBus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MCPServer implements MCPServerInterface
{
    private array $tools = [];

    private array $resources = [];

    private ?string $currentConversationId = null;

    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly ResourceManager $resourceManager,
        /** @phpstan-ignore-next-line */
        private readonly ?CommandBus $commandBus = null,
        /** @phpstan-ignore-next-line */
        private readonly ?DomainEventBus $eventBus = null
    ) {
        $this->initializeServer();
    }

    private function initializeServer(): void
    {
        // Load tools from registry
        $this->tools = $this->toolRegistry->getAllTools();

        // Load resources from manager
        $this->resources = $this->resourceManager->getAllResources();
    }

    public function handle(MCPRequest $request): MCPResponse
    {
        try {
            // Start or resume conversation
            $this->ensureConversation($request);

            // Route the request to appropriate handler
            return match ($request->getMethod()) {
                'initialize'     => $this->handleInitialize($request),
                'tools/list'     => $this->handleToolsList($request),
                'tools/call'     => $this->handleToolCall($request),
                'resources/list' => $this->handleResourcesList($request),
                'resources/read' => $this->handleResourceRead($request),
                'prompts/list'   => $this->handlePromptsList($request),
                'prompts/get'    => $this->handlePromptGet($request),
                'completion'     => $this->handleCompletion($request),
                default          => throw new MCPException("Unknown method: {$request->getMethod()}"),
            };
        } catch (\Exception $e) {
            Log::error('MCP Server error', [
                'method' => $request->getMethod(),
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);

            return MCPResponse::error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    private function ensureConversation(MCPRequest $request): void
    {
        $conversationId = $request->getConversationId() ?? Str::uuid()->toString();

        if ($conversationId !== $this->currentConversationId) {
            $this->currentConversationId = $conversationId;

            // Create or load conversation aggregate
            $aggregate = AIInteractionAggregate::retrieve($conversationId);

            if (! $aggregate->isActive()) {
                $aggregate->startConversation(
                    $conversationId,
                    'mcp-server',
                    $request->getUserId(),
                    ['source' => 'mcp', 'version' => '1.0']
                );
                $aggregate->persist();
            }
        }
    }

    private function handleInitialize(MCPRequest $request): MCPResponse
    {
        return MCPResponse::success([
            'protocolVersion' => '1.0',
            'capabilities'    => $this->getCapabilities(),
            'serverInfo'      => [
                'name'        => 'FinAegis MCP Server',
                'version'     => '1.0.0',
                'description' => 'AI-powered banking operations via Model Context Protocol',
            ],
        ]);
    }

    private function handleToolsList(MCPRequest $request): MCPResponse
    {
        // Get fresh tools from registry
        $this->tools = $this->toolRegistry->getAllTools();

        $tools = [];

        foreach ($this->tools as $name => $tool) {
            // Handle both tool objects and null values
            if ($tool instanceof MCPToolInterface) {
                $tools[] = [
                    'name'         => $tool->getName(),
                    'description'  => $tool->getDescription(),
                    'inputSchema'  => $tool->getInputSchema(),
                    'outputSchema' => $tool->getOutputSchema(),
                ];
            }
        }

        // Cache the tools list for performance
        if ($this->currentConversationId) {
            Cache::put("mcp:tools:list:{$this->currentConversationId}", $tools, 300);
        }

        return MCPResponse::success(['tools' => $tools]);
    }

    private function handleToolCall(MCPRequest $request): MCPResponse
    {
        $params = $request->getParams();
        $toolName = $params['name'] ?? throw new MCPException('Tool name is required');
        $arguments = $params['arguments'] ?? [];

        // Add user_uuid from request if not in arguments
        // Convert user ID to UUID format if it's numeric
        if (! isset($arguments['user_uuid']) && $request->getUserId()) {
            $userId = $request->getUserId();
            // If userId is numeric, get the user's UUID
            if (is_numeric($userId)) {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    $arguments['user_uuid'] = $user->uuid;
                }
            } else {
                $arguments['user_uuid'] = $userId;
            }
        }

        // Get fresh tools from registry
        $this->tools = $this->toolRegistry->getAllTools();

        // Check if tool exists
        if (! isset($this->tools[$toolName])) {
            throw new ToolNotFoundException("Tool not found: {$toolName}");
        }

        $tool = $this->tools[$toolName];

        // Validate input against schema first (before authorization)
        $this->validateToolInput($tool, $arguments);

        // Additional validation using tool's validateInput method
        if (! $tool->validateInput($arguments)) {
            throw new \InvalidArgumentException("Tool input validation failed for: {$toolName}");
        }

        // Check authorization after validation
        $userId = $arguments['user_uuid'] ?? null;

        // Check authorization - the tool decides if it needs a user
        if (! $tool->authorize($userId)) {
            // Determine the type of failure based on context
            // If there's no user ID and no authenticated user, this might be a "not found" case
            // But we need to check if the tool actually requires authentication
            if (! $userId && ! auth()->check()) {
                // Check if this tool typically requires auth by looking at its input schema
                $schema = $tool->getInputSchema();
                $requiresUser = isset($schema['properties']['user_uuid']) ||
                               in_array('user_uuid', $schema['required'] ?? []);

                // If the tool expects a user and none is provided, it's a "not found/not authenticated" issue
                if ($requiresUser) {
                    // Return an error response that will be handled by the handle() method
                    throw new MCPException('User not found or not authenticated');
                }
            }

            // Otherwise it's a proper authorization failure
            throw new \Illuminate\Auth\Access\AuthorizationException(
                "User is not authorized to use tool: {$toolName}"
            );
        }

        // Execute tool with timing
        $startTime = microtime(true);

        try {
            // Execute through command bus for CQRS pattern
            $result = $this->executeToolWithEventSourcing($tool, $arguments);

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            // Record tool execution in event store
            $this->recordToolExecution($toolName, $arguments, $result, $duration);

            // Check if tool execution failed
            if (! $result->isSuccess()) {
                return MCPResponse::error($result->getError() ?? 'Tool execution failed');
            }

            return MCPResponse::success([
                'toolResult' => $result->getData(),
                'metadata'   => [
                    'duration_ms'     => $duration,
                    'cache_hit'       => $result->wasCached(),
                    'conversation_id' => $this->currentConversationId,
                ],
            ]);
        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            // Record failed execution
            $this->recordToolExecution(
                $toolName,
                $arguments,
                ToolExecutionResult::failure($e->getMessage()),
                $duration
            );

            throw $e;
        }
    }

    private function executeToolWithEventSourcing(
        MCPToolInterface $tool,
        array $arguments
    ): ToolExecutionResult {
        // Check cache first
        $cacheKey = $this->getCacheKey($tool->getName(), $arguments);

        if ($tool->isCacheable() && Cache::has($cacheKey)) {
            return ToolExecutionResult::fromCache(Cache::get($cacheKey));
        }

        // Execute the tool
        $result = $tool->execute($arguments, $this->currentConversationId);

        // Cache if applicable
        if ($tool->isCacheable() && $result->isSuccess()) {
            Cache::put($cacheKey, $result->getData(), $tool->getCacheTtl());
        }

        return $result;
    }

    private function recordToolExecution(
        string $toolName,
        array $parameters,
        ToolExecutionResult $result,
        int $duration
    ): void {
        $aggregate = AIInteractionAggregate::retrieve($this->currentConversationId);

        $aggregate->executeTool($toolName, $parameters, $result);
        $aggregate->persist();
    }

    private function handleResourcesList(MCPRequest $request): MCPResponse
    {
        $resources = [];

        foreach ($this->resources as $uri => $resource) {
            $resources[] = [
                'uri'         => $uri,
                'name'        => $resource->getName(),
                'description' => $resource->getDescription(),
                'mimeType'    => $resource->getMimeType(),
            ];
        }

        return MCPResponse::success(['resources' => $resources]);
    }

    private function handleResourceRead(MCPRequest $request): MCPResponse
    {
        $params = $request->getParams();
        $uri = $params['uri'] ?? throw new MCPException('Resource URI is required');

        if (! isset($this->resources[$uri])) {
            throw new MCPException("Resource not found: {$uri}");
        }

        $resource = $this->resources[$uri];
        $content = $resource->read($params);

        return MCPResponse::success([
            'contents' => [
                [
                    'uri'      => $uri,
                    'mimeType' => $resource->getMimeType(),
                    'text'     => $content,
                ],
            ],
        ]);
    }

    private function handlePromptsList(MCPRequest $request): MCPResponse
    {
        return MCPResponse::success([
            'prompts' => [
                [
                    'name'        => 'account_balance',
                    'description' => 'Check account balance for a user',
                    'arguments'   => [
                        ['name' => 'account_id', 'description' => 'Account UUID', 'required' => true],
                    ],
                ],
                [
                    'name'        => 'transfer_funds',
                    'description' => 'Transfer funds between accounts',
                    'arguments'   => [
                        ['name' => 'from_account', 'description' => 'Source account UUID', 'required' => true],
                        ['name' => 'to_account', 'description' => 'Destination account UUID', 'required' => true],
                        ['name' => 'amount', 'description' => 'Amount to transfer', 'required' => true],
                        ['name' => 'currency', 'description' => 'Currency code', 'required' => true],
                    ],
                ],
            ],
        ]);
    }

    private function handlePromptGet(MCPRequest $request): MCPResponse
    {
        $params = $request->getParams();
        $name = $params['name'] ?? throw new MCPException('Prompt name is required');

        // Return prompt template based on name
        $prompts = $this->getPromptTemplates();

        if (! isset($prompts[$name])) {
            throw new MCPException("Prompt not found: {$name}");
        }

        return MCPResponse::success($prompts[$name]);
    }

    private function handleCompletion(MCPRequest $request): MCPResponse
    {
        // This would integrate with AI model for completions
        // For now, return a placeholder response
        return MCPResponse::success([
            'completion' => [
                'text'  => 'AI completion would be generated here',
                'model' => 'gpt-4',
            ],
        ]);
    }

    private function validateToolInput(MCPToolInterface $tool, array $input): void
    {
        $schema = $tool->getInputSchema();
        $required = $schema['required'] ?? [];

        foreach ($required as $field) {
            if (! isset($input[$field])) {
                throw new MCPException("Required field missing: {$field}");
            }
        }

        // Additional validation based on schema properties
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $field => $rules) {
                if (isset($input[$field])) {
                    // Skip validation for user_uuid since we handle it specially
                    if ($field === 'user_uuid') {
                        continue;
                    }
                    $this->validateField($field, $input[$field], $rules);
                }
            }
        }
    }

    private function validateField(string $field, mixed $value, array $rules): void
    {
        // Type validation
        if (isset($rules['type'])) {
            $type = $rules['type'];
            $valid = match ($type) {
                'string'  => is_string($value),
                'number'  => is_numeric($value),
                'integer' => is_int($value),
                'boolean' => is_bool($value),
                'array'   => is_array($value),
                'object'  => is_object($value) || is_array($value),
                default   => true,
            };

            if (! $valid) {
                throw new MCPException("Field {$field} must be of type {$type}");
            }
        }

        // Enum validation
        if (isset($rules['enum']) && ! in_array($value, $rules['enum'], true)) {
            throw new MCPException("Field {$field} must be one of: " . implode(', ', $rules['enum']));
        }

        // Pattern validation
        if (isset($rules['pattern']) && is_string($value)) {
            $pattern = '/' . str_replace('/', '\\/', $rules['pattern']) . '/';
            if (! preg_match($pattern, $value)) {
                throw new MCPException("Field {$field} does not match required pattern");
            }
        }
    }

    private function getCacheKey(string $toolName, array $arguments): string
    {
        return sprintf(
            'mcp:tool:%s:%s:%s',
            $this->currentConversationId,
            $toolName,
            md5(json_encode($arguments) ?: '')
        );
    }

    public function getCapabilities(): array
    {
        return [
            'tools'        => true,
            'resources'    => true,
            'prompts'      => true,
            'sampling'     => true,
            'logging'      => true,
            'experimental' => [
                'streaming'   => false,
                'attachments' => false,
            ],
        ];
    }

    public function listTools(): array
    {
        // Get fresh tools from registry
        $this->tools = $this->toolRegistry->getAllTools();

        $tools = [];
        foreach ($this->tools as $name => $tool) {
            if ($tool instanceof MCPToolInterface) {
                $tools[] = [
                    'name'        => $tool->getName(),
                    'description' => $tool->getDescription(),
                ];
            }
        }

        return $tools;
    }

    public function listResources(): array
    {
        return array_values($this->resources);
    }

    public function executeTool(string $name, array $arguments): array
    {
        $request = MCPRequest::create('tools/call', [
            'name'      => $name,
            'arguments' => $arguments,
        ]);

        $response = $this->handleToolCall($request);

        if ($response->isError()) {
            throw new \Exception($response->getError());
        }

        return $response->getData()['toolResult'] ?? [];
    }

    public function readResource(string $uri): array
    {
        $request = MCPRequest::create('resources/read', [
            'uri' => $uri,
        ]);

        $response = $this->handleResourceRead($request);

        if ($response->isError()) {
            throw new \Exception($response->getError());
        }

        return $response->getData()['contents'] ?? [];
    }

    private function getPromptTemplates(): array
    {
        return [
            'account_balance' => [
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'You are a helpful banking assistant. ' .
                                     'Provide account balance information clearly.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => 'Check the balance for account {{account_id}}',
                    ],
                ],
            ],
            'transfer_funds' => [
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'You are processing a funds transfer. Ensure all details are correct.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => 'Transfer {{amount}} {{currency}} from {{from_account}} to {{to_account}}',
                    ],
                ],
            ],
        ];
    }
}
