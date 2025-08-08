<?php

declare(strict_types=1);

namespace App\Domain\AI\Contracts;

use App\Domain\AI\ValueObjects\ToolExecutionResult;

interface MCPToolInterface
{
    /**
     * Get the tool name (must be unique).
     */
    public function getName(): string;

    /**
     * Get the tool category for organization.
     */
    public function getCategory(): string;

    /**
     * Get a human-readable description of what the tool does.
     */
    public function getDescription(): string;

    /**
     * Get the JSON schema for input validation.
     */
    public function getInputSchema(): array;

    /**
     * Get the JSON schema for output format.
     */
    public function getOutputSchema(): array;

    /**
     * Execute the tool with given parameters.
     */
    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult;

    /**
     * Get tool capabilities/features.
     */
    public function getCapabilities(): array;

    /**
     * Check if tool results can be cached.
     */
    public function isCacheable(): bool;

    /**
     * Get cache TTL in seconds.
     */
    public function getCacheTtl(): int;

    /**
     * Validate input parameters.
     */
    public function validateInput(array $parameters): bool;

    /**
     * Check if user has permission to use this tool.
     */
    public function authorize(?string $userId): bool;
}
