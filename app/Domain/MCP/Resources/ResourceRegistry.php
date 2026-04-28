<?php

declare(strict_types=1);

namespace App\Domain\MCP\Resources;

use App\Domain\MCP\Resources\Contracts\McpResourceInterface;

/**
 * In-memory registry of McpResourceInterface implementations.
 *
 * Keyed by URI template (e.g. `account://balance/{currency}`). `resolve()`
 * matches a concrete URI back to the template + extracts placeholder params.
 */
final class ResourceRegistry
{
    /**
     * @var array<string, McpResourceInterface>
     */
    private array $byTemplate = [];

    public function register(McpResourceInterface $resource): void
    {
        $this->byTemplate[$resource->uriTemplate()] = $resource;
    }

    /**
     * @return array<int, McpResourceInterface>
     */
    public function all(): array
    {
        return array_values($this->byTemplate);
    }

    /**
     * Resolve a concrete URI like `account://balance/USD` back to its
     * registered template + the captured placeholder params. Returns null if
     * no template matches.
     *
     * Templates without placeholders match exactly (`account://profile`).
     * Placeholders match a single non-`/` segment.
     *
     * @return array{0: McpResourceInterface, 1: array<string, string>}|null
     */
    public function resolve(string $uri): ?array
    {
        foreach ($this->byTemplate as $tpl => $res) {
            $regex = '#^' . preg_replace_callback(
                '/\\\\\{(\w+)\\\\\}/',
                static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
                preg_quote($tpl, '#'),
            ) . '$#';

            if (preg_match($regex, $uri, $matches)) {
                /** @var array<string, string> $params */
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                return [$res, $params];
            }
        }

        return null;
    }
}
