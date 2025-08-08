<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\Exceptions\ToolAlreadyRegisteredException;
use App\Domain\AI\Exceptions\ToolNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ToolRegistry
{
    private Collection $tools;

    private Collection $categories;

    public function __construct()
    {
        $this->tools = new Collection();
        $this->categories = new Collection();
    }

    public function register(MCPToolInterface $tool): void
    {
        $name = $tool->getName();

        if ($this->tools->has($name)) {
            throw new ToolAlreadyRegisteredException("Tool already registered: {$name}");
        }

        $this->tools->put($name, $tool);

        // Organize by category
        $category = $tool->getCategory();
        if (! $this->categories->has($category)) {
            $this->categories->put($category, new Collection());
        }
        $this->categories->get($category)->push($tool);

        Log::info('MCP Tool registered', [
            'name'        => $name,
            'category'    => $category,
            'description' => $tool->getDescription(),
        ]);
    }

    public function unregister(string $name): void
    {
        if (! $this->tools->has($name)) {
            throw new ToolNotFoundException("Tool not found: {$name}");
        }

        $tool = $this->tools->get($name);
        $this->tools->forget($name);

        // Remove from category
        $category = $tool->getCategory();
        if ($this->categories->has($category)) {
            $this->categories->get($category)->reject(fn ($t) => $t->getName() === $name);
        }

        Log::info('MCP Tool unregistered', ['name' => $name]);
    }

    public function get(string $name): MCPToolInterface
    {
        if (! $this->tools->has($name)) {
            throw new ToolNotFoundException("Tool not found: {$name}");
        }

        return $this->tools->get($name);
    }

    public function has(string $name): bool
    {
        return $this->tools->has($name);
    }

    public function getAllTools(): array
    {
        return $this->tools->all();
    }

    public function getToolsByCategory(string $category): Collection
    {
        return $this->categories->get($category, new Collection());
    }

    public function getCategories(): array
    {
        return $this->categories->keys()->all();
    }

    public function searchTools(string $query): Collection
    {
        $query = strtolower($query);

        return $this->tools->filter(function (MCPToolInterface $tool) use ($query) {
            return str_contains(strtolower($tool->getName()), $query) ||
                   str_contains(strtolower($tool->getDescription()), $query) ||
                   str_contains(strtolower($tool->getCategory()), $query);
        });
    }

    public function getToolsWithCapability(string $capability): Collection
    {
        return $this->tools->filter(function (MCPToolInterface $tool) use ($capability) {
            $capabilities = $tool->getCapabilities();

            return in_array($capability, $capabilities, true);
        });
    }

    public function exportSchema(): array
    {
        $schema = [
            'version'    => '1.0',
            'tools'      => [],
            'categories' => [],
        ];

        foreach ($this->tools as $name => $tool) {
            $schema['tools'][] = [
                'name'         => $name,
                'category'     => $tool->getCategory(),
                'description'  => $tool->getDescription(),
                'inputSchema'  => $tool->getInputSchema(),
                'outputSchema' => $tool->getOutputSchema(),
                'capabilities' => $tool->getCapabilities(),
                'cacheable'    => $tool->isCacheable(),
                'cacheTtl'     => $tool->getCacheTtl(),
            ];
        }

        foreach ($this->categories as $category => $tools) {
            $schema['categories'][] = [
                'name'      => $category,
                'toolCount' => $tools->count(),
                'tools'     => $tools->map(fn ($t) => $t->getName())->values()->all(),
            ];
        }

        return $schema;
    }

    public function getStatistics(): array
    {
        return [
            'total_tools'       => $this->tools->count(),
            'categories'        => $this->categories->count(),
            'tools_by_category' => $this->categories->map(fn ($tools) => $tools->count())->all(),
            'cacheable_tools'   => $this->tools->filter(fn ($tool) => $tool->isCacheable())->count(),
        ];
    }
}
