<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\MCP;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\MCP\Server\McpToolAdapter;
use Tests\TestCase;

uses(TestCase::class);

function adapterStubTool(string $name, callable $exec): MCPToolInterface
{
    return new class ($name, $exec) implements MCPToolInterface {
        /** @var callable */
        private $exec;

        public function __construct(private string $name, callable $exec)
        {
            $this->exec = $exec;
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function getCategory(): string
        {
            return 'test';
        }

        public function getDescription(): string
        {
            return 'Adapter stub';
        }

        /** @return array<string, mixed> */
        public function getInputSchema(): array
        {
            return ['type' => 'object'];
        }

        /** @return array<string, mixed> */
        public function getOutputSchema(): array
        {
            return ['type' => 'object'];
        }

        /**
         * @param  array<string, mixed>  $parameters
         */
        public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
        {
            return ($this->exec)($parameters, $conversationId);
        }

        /** @return array<int|string, mixed> */
        public function getCapabilities(): array
        {
            return [];
        }

        public function isCacheable(): bool
        {
            return false;
        }

        public function getCacheTtl(): int
        {
            return 0;
        }

        /**
         * @param  array<string, mixed>  $parameters
         */
        public function validateInput(array $parameters): bool
        {
            return true;
        }

        public function authorize(?string $userId): bool
        {
            return true;
        }
    };
}

it('adapts a successful tool result into JSON-RPC content with structuredContent', function () {
    $tool = adapterStubTool('echo', fn (array $p) => ToolExecutionResult::success(['echoed' => $p['x'] ?? null]));

    $out = (new McpToolAdapter())->execute($tool, ['x' => 7], 'conv_test');

    expect($out)->toHaveKey('content');
    expect($out['content'])->toBeArray();
    expect($out['content'][0]['type'])->toBe('text');
    expect(json_decode($out['content'][0]['text'], true))->toBe(['echoed' => 7]);
    expect($out['isError'])->toBeFalse();
    expect($out['structuredContent'])->toBe(['echoed' => 7]);
});

it('flags errored tool results with isError=true and surfaces the error message in content', function () {
    $tool = adapterStubTool('boom', fn () => ToolExecutionResult::failure('Boom went wrong'));

    $out = (new McpToolAdapter())->execute($tool, [], 'conv');

    expect($out['isError'])->toBeTrue();
    expect($out['content'][0]['type'])->toBe('text');
    expect($out['content'][0]['text'])->toContain('Boom went wrong');
    expect($out)->not->toHaveKey('structuredContent'); // omitted on error
});

it('falls back to a generic error string when getError() returns null', function () {
    // Hand-build a failure result with explicit null error to exercise the `??` fallback.
    $tool = adapterStubTool('silent', fn () => new ToolExecutionResult(false, [], null, 0));

    $out = (new McpToolAdapter())->execute($tool, [], null);

    expect($out['isError'])->toBeTrue();
    expect($out['content'][0]['text'])->toBe('Tool execution failed');
});
