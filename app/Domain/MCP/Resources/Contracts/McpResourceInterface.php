<?php

declare(strict_types=1);

namespace App\Domain\MCP\Resources\Contracts;

/**
 * MCP read-context primitive.
 *
 * A Resource is a read-only document the server makes available to clients,
 * identified by a URI template. The client lists resources via `resources/list`
 * and dereferences a concrete URI via `resources/read`. Unlike tools, resources
 * never produce side effects and don't take an idempotency key.
 *
 * URI template syntax follows RFC 6570 lite: `{name}` placeholders match a
 * single path segment. ResourceRegistry::resolve() handles the matching.
 */
interface McpResourceInterface
{
    /**
     * The URI template, e.g. `account://balance/{currency}` — used both as the
     * registry key and what `resources/list` advertises.
     */
    public function uriTemplate(): string;

    public function name(): string;

    public function description(): string;

    public function mimeType(): string;

    /**
     * The OAuth scope required to read this resource, or null for unscoped.
     * Resources lacking the scope are filtered from `resources/list` and
     * return -32000 INSUFFICIENT_SCOPE on `resources/read`.
     */
    public function scope(): ?string;

    /**
     * Render the resource body for a given (params, user) pair.
     *
     * @param  array<string, string> $params  URI template params, e.g. ['currency' => 'USD']
     * @return string  Resource body (typically JSON)
     */
    public function read(array $params, ?int $userId): string;
}
