<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SdkGenerateCommandTest extends TestCase
{
    private string $outputPath;

    private string $specPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputPath = storage_path('app/sdk-test');
        $this->specPath = storage_path('api-docs/api-docs-test.json');

        // Create a minimal OpenAPI spec for testing
        File::ensureDirectoryExists(dirname($this->specPath));
        File::put($this->specPath, json_encode([
            'openapi' => '3.0.0',
            'info'    => ['title' => 'FinAegis API', 'version' => '3.4.0'],
            'paths'   => [
                '/api/v1/accounts' => [
                    'get' => [
                        'tags'        => ['Accounts'],
                        'operationId' => 'listAccounts',
                        'summary'     => 'List all accounts',
                    ],
                ],
                '/api/v1/accounts/{id}' => [
                    'get' => [
                        'tags'        => ['Accounts'],
                        'operationId' => 'getAccount',
                        'summary'     => 'Get account by ID',
                    ],
                ],
                '/api/v1/transfers' => [
                    'post' => [
                        'tags'        => ['Transfers'],
                        'operationId' => 'createTransfer',
                        'summary'     => 'Create a transfer',
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'Account' => [
                        'type'       => 'object',
                        'properties' => [
                            'id'       => ['type' => 'string'],
                            'balance'  => ['type' => 'number'],
                            'currency' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->outputPath);
        File::delete($this->specPath);

        parent::tearDown();
    }

    public function test_generates_typescript_sdk(): void
    {
        $this->artisan('sdk:generate', [
            'language' => 'typescript',
            '--output' => $this->outputPath,
            '--spec'   => $this->specPath,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('TypeScript/JavaScript');

        $this->assertFileExists("{$this->outputPath}/typescript/FinAegisClient.ts");
        $this->assertFileExists("{$this->outputPath}/typescript/Models.ts");
        $this->assertFileExists("{$this->outputPath}/typescript/README.md");

        $client = File::get("{$this->outputPath}/typescript/FinAegisClient.ts");
        $this->assertStringContainsString('listAccounts', $client);
        $this->assertStringContainsString('createTransfer', $client);
    }

    public function test_generates_python_sdk(): void
    {
        $this->artisan('sdk:generate', [
            'language' => 'python',
            '--output' => $this->outputPath,
            '--spec'   => $this->specPath,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Python');

        $this->assertFileExists("{$this->outputPath}/python/FinAegisClient.py");
        $this->assertFileExists("{$this->outputPath}/python/Models.py");

        $client = File::get("{$this->outputPath}/python/FinAegisClient.py");
        $this->assertStringContainsString('list_accounts', $client);
        $this->assertStringContainsString('create_transfer', $client);
    }

    public function test_rejects_unsupported_language(): void
    {
        $this->artisan('sdk:generate', [
            'language' => 'rust',
            '--output' => $this->outputPath,
            '--spec'   => $this->specPath,
        ])
            ->assertFailed()
            ->expectsOutputToContain('Unsupported language: rust');
    }

    public function test_fails_when_spec_not_found(): void
    {
        $this->artisan('sdk:generate', [
            'language' => 'typescript',
            '--output' => $this->outputPath,
            '--spec'   => '/nonexistent/spec.json',
        ])
            ->assertFailed()
            ->expectsOutputToContain('OpenAPI spec not found');
    }

    public function test_readme_contains_endpoint_listing(): void
    {
        $this->artisan('sdk:generate', [
            'language' => 'typescript',
            '--output' => $this->outputPath,
            '--spec'   => $this->specPath,
        ])->assertSuccessful();

        $readme = File::get("{$this->outputPath}/typescript/README.md");
        $this->assertStringContainsString('Accounts', $readme);
        $this->assertStringContainsString('Transfers', $readme);
        $this->assertStringContainsString('GET /api/v1/accounts', $readme);
    }

    public function test_models_file_contains_schema(): void
    {
        $this->artisan('sdk:generate', [
            'language' => 'typescript',
            '--output' => $this->outputPath,
            '--spec'   => $this->specPath,
        ])->assertSuccessful();

        $models = File::get("{$this->outputPath}/typescript/Models.ts");
        $this->assertStringContainsString('Account', $models);
        $this->assertStringContainsString('balance', $models);
    }
}
