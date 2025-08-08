<?php

declare(strict_types=1);

namespace App\Domain\AI\Contracts;

use App\Domain\AI\ValueObjects\MCPRequest;
use App\Domain\AI\ValueObjects\MCPResponse;

interface MCPServerInterface
{
    /**
     * Handle an MCP request and return a response.
     */
    public function handle(MCPRequest $request): MCPResponse;

    /**
     * Get server capabilities.
     */
    public function getCapabilities(): array;

    /**
     * List all available tools.
     */
    public function listTools(): array;

    /**
     * List all available resources.
     */
    public function listResources(): array;

    /**
     * Execute a specific tool.
     */
    public function executeTool(string $name, array $arguments): array;

    /**
     * Read a specific resource.
     */
    public function readResource(string $uri): array;
}
