<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

use App\Domain\AI\Contracts\MCPToolInterface;

final class McpToolAdapter
{
    /**
     * Execute an internal MCP tool and adapt its ToolExecutionResult into the
     * JSON-RPC `tools/call` response content shape (text content array +
     * isError flag + structuredContent for typed clients).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(MCPToolInterface $tool, array $params, ?string $conversationId): array
    {
        $result = $tool->execute($params, $conversationId);

        if (! $result->isSuccess()) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $result->getError() ?? 'Tool execution failed',
                    ],
                ],
                'isError' => true,
            ];
        }

        $payload = $result->getData();

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ],
            ],
            'isError'           => false,
            'structuredContent' => $payload,
        ];
    }
}
