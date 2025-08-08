<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP;

use App\Domain\AI\Contracts\MCPResourceInterface;
use Illuminate\Support\Collection;

class ResourceManager
{
    private Collection $resources;

    public function __construct()
    {
        $this->resources = new Collection();
    }

    public function register(MCPResourceInterface $resource): void
    {
        $this->resources->put($resource->getUri(), $resource);
    }

    public function unregister(string $uri): void
    {
        $this->resources->forget($uri);
    }

    public function get(string $uri): ?MCPResourceInterface
    {
        return $this->resources->get($uri);
    }

    public function has(string $uri): bool
    {
        return $this->resources->has($uri);
    }

    public function getAllResources(): array
    {
        return $this->resources->all();
    }

    public function searchResources(string $query): Collection
    {
        $query = strtolower($query);

        return $this->resources->filter(function (MCPResourceInterface $resource) use ($query) {
            return str_contains(strtolower($resource->getName()), $query) ||
                   str_contains(strtolower($resource->getDescription()), $query);
        });
    }
}
