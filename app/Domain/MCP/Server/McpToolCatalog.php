<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

/**
 * Which public MCP tools are available, from the `mcp.tools` catalog.
 *
 * A tool is available when its `enabled` flag is on and, when the entry
 * names a `requires_config` key, that config value is on as well (for
 * example exchange.trade needs `trading.enabled`, so TRADING_ENABLED=false
 * turns the tool off even when MCP_TOOL_EXCHANGE_TRADE=true). The JSON-RPC
 * router (tools/list and tools/call) and the public pages that count the
 * tools all use this, so they agree.
 */
final class McpToolCatalog
{
    public static function isEnabled(mixed $entry): bool
    {
        if (! is_array($entry) || ! (bool) ($entry['enabled'] ?? false)) {
            return false;
        }

        $requires = $entry['requires_config'] ?? null;

        return ! is_string($requires) || $requires === '' || (bool) config($requires);
    }

    /**
     * The available catalog entries, keyed by public tool name.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function enabledTools(): array
    {
        $catalog = config('mcp.tools');
        $enabled = [];

        foreach (is_array($catalog) ? $catalog : [] as $name => $entry) {
            if (self::isEnabled($entry)) {
                /** @var array<string, mixed> $entry */
                $enabled[(string) $name] = $entry;
            }
        }

        return $enabled;
    }
}
