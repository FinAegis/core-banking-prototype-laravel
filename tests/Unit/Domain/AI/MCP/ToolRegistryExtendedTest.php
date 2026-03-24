<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\MCP;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\Exceptions\ToolAlreadyRegisteredException;
use App\Domain\AI\Exceptions\ToolNotFoundException;
use App\Domain\AI\MCP\ToolRegistry;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ToolRegistryExtendedTest extends TestCase
{
    private ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ToolRegistry();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @return MCPToolInterface&MockInterface
     */
    private function createMockTool(string $name, string $description = 'Test tool', string $category = 'test'): MCPToolInterface
    {
        /** @var MCPToolInterface&MockInterface $tool */
        $tool = Mockery::mock(MCPToolInterface::class);
        $tool->shouldReceive('getName')->andReturn($name);
        $tool->shouldReceive('getDescription')->andReturn($description);
        $tool->shouldReceive('getCategory')->andReturn($category);
        $tool->shouldReceive('getParameters')->andReturn([]);
        $tool->shouldReceive('getCapabilities')->andReturn(['read']);
        $tool->shouldReceive('getInputSchema')->andReturn([]);
        $tool->shouldReceive('getOutputSchema')->andReturn([]);
        $tool->shouldReceive('isCacheable')->andReturn(false);
        $tool->shouldReceive('getCacheTtl')->andReturn(0);

        return $tool;
    }

    public function test_search_tools_finds_by_name(): void
    {
        $tool = $this->createMockTool('account.balance', 'Check account balance', 'account');
        $this->registry->register($tool);

        $results = $this->registry->searchTools('balance');

        expect($results->count())->toBeGreaterThanOrEqual(1);
    }

    public function test_search_tools_finds_by_description(): void
    {
        $tool = $this->createMockTool('test.tool', 'Retrieve cryptocurrency prices', 'exchange');
        $this->registry->register($tool);

        $results = $this->registry->searchTools('cryptocurrency');

        expect($results->count())->toBeGreaterThanOrEqual(1);
    }

    public function test_search_tools_returns_empty_for_no_match(): void
    {
        $tool = $this->createMockTool('simple.tool', 'Simple description', 'simple');
        $this->registry->register($tool);

        $results = $this->registry->searchTools('zzz_nonexistent_zzz');

        expect($results->count())->toBe(0);
    }

    public function test_get_categories_returns_unique_categories(): void
    {
        $tool1 = $this->createMockTool('tool1', 'Tool 1', 'account');
        $tool2 = $this->createMockTool('tool2', 'Tool 2', 'payment');
        $tool3 = $this->createMockTool('tool3', 'Tool 3', 'account');

        $this->registry->register($tool1);
        $this->registry->register($tool2);
        $this->registry->register($tool3);

        $categories = $this->registry->getCategories();

        expect($categories)->toContain('account');
        expect($categories)->toContain('payment');
        expect(count(array_unique($categories)))->toBe(count($categories));
    }

    public function test_get_tools_by_category_filters_correctly(): void
    {
        $tool1 = $this->createMockTool('acct.create', 'Create account', 'account');
        $tool2 = $this->createMockTool('pay.send', 'Send payment', 'payment');
        $tool3 = $this->createMockTool('acct.balance', 'Check balance', 'account');

        $this->registry->register($tool1);
        $this->registry->register($tool2);
        $this->registry->register($tool3);

        $accountTools = $this->registry->getToolsByCategory('account');

        expect($accountTools->count())->toBe(2);
    }

    public function test_export_schema_returns_correct_structure(): void
    {
        $tool = $this->createMockTool('test.export', 'Export test', 'test');
        $this->registry->register($tool);

        $schema = $this->registry->exportSchema();

        expect($schema)->toBeArray();
        expect($schema)->toHaveKey('tools');
        expect($schema)->toHaveKey('version');
    }

    public function test_get_statistics_returns_counts(): void
    {
        $tool1 = $this->createMockTool('stats.tool1', 'Tool 1', 'cat1');
        $tool2 = $this->createMockTool('stats.tool2', 'Tool 2', 'cat2');

        $this->registry->register($tool1);
        $this->registry->register($tool2);

        $stats = $this->registry->getStatistics();

        expect($stats)->toBeArray();
        expect($stats)->toHaveKey('total_tools');
        expect($stats['total_tools'])->toBe(2);
    }

    public function test_register_duplicate_throws_exception(): void
    {
        $tool = $this->createMockTool('duplicate.tool');
        $this->registry->register($tool);

        $this->expectException(ToolAlreadyRegisteredException::class);

        $duplicate = $this->createMockTool('duplicate.tool');
        $this->registry->register($duplicate);
    }

    public function test_unregister_nonexistent_throws_exception(): void
    {
        $this->expectException(ToolNotFoundException::class);

        $this->registry->unregister('nonexistent.tool');
    }

    public function test_get_nonexistent_throws_exception(): void
    {
        $this->expectException(ToolNotFoundException::class);

        $this->registry->get('nonexistent.tool');
    }

    public function test_has_returns_correct_boolean(): void
    {
        $tool = $this->createMockTool('exists.tool');
        $this->registry->register($tool);

        expect($this->registry->has('exists.tool'))->toBeTrue();
        expect($this->registry->has('missing.tool'))->toBeFalse();
    }

    public function test_get_all_tools_returns_all_registered(): void
    {
        $tool1 = $this->createMockTool('all.tool1');
        $tool2 = $this->createMockTool('all.tool2');

        $this->registry->register($tool1);
        $this->registry->register($tool2);

        $all = $this->registry->getAllTools();

        expect($all)->toBeArray();
        expect(count($all))->toBe(2);
    }

    public function test_unregister_removes_tool(): void
    {
        $tool = $this->createMockTool('removable.tool');
        $this->registry->register($tool);

        expect($this->registry->has('removable.tool'))->toBeTrue();

        $this->registry->unregister('removable.tool');

        expect($this->registry->has('removable.tool'))->toBeFalse();
    }
}
