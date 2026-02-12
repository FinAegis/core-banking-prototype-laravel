<?php

declare(strict_types=1);

namespace App\Domain\FinancialInstitution\Services;

use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Generates partner-branded SDK packages for API integration.
 */
class SdkGeneratorService
{
    public function __construct(
        private readonly PartnerTierService $tierService,
    ) {
    }

    /**
     * Generate an SDK for a partner in the given language.
     *
     * @return array{success: bool, message: string, path: string|null, language: string}
     */
    public function generate(FinancialInstitutionPartner $partner, string $language): array
    {
        $tier = $this->tierService->getPartnerTier($partner);

        if (! $tier->hasSdkAccess()) {
            return [
                'success'  => false,
                'message'  => "SDK access requires Growth or Enterprise tier. Current tier: {$tier->label()}",
                'path'     => null,
                'language' => $language,
            ];
        }

        $languages = $this->getAvailableLanguages();

        if (! isset($languages[$language])) {
            return [
                'success'  => false,
                'message'  => "Unsupported language: {$language}",
                'path'     => null,
                'language' => $language,
            ];
        }

        if (config('baas.demo_mode', true)) {
            return $this->generateDemoSdk($partner, $language);
        }

        // Production: would invoke openapi-generator-cli
        return $this->generateDemoSdk($partner, $language);
    }

    /**
     * Generate a demo SDK with template files.
     *
     * @return array{success: bool, message: string, path: string|null, language: string}
     */
    public function generateDemoSdk(FinancialInstitutionPartner $partner, string $language): array
    {
        $partnerCode = $partner->partner_code;
        $outputPath = config('baas.sdk.output_path') . "/{$partnerCode}/{$language}";

        File::ensureDirectoryExists($outputPath);

        $langConfig = config("baas.sdk.supported_languages.{$language}");
        $apiVersion = config('baas.sdk.api_version', 'v1');

        // Generate README
        File::put("{$outputPath}/README.md", $this->generateReadme($partner, $language, $langConfig));

        // Generate client class
        $ext = $langConfig['extension'] ?? $language;
        File::put(
            "{$outputPath}/FinAegisClient.{$ext}",
            $this->generateClientClass($partner, $language, $langConfig, $apiVersion),
        );

        // Generate auth helper
        File::put(
            "{$outputPath}/Auth.{$ext}",
            $this->generateAuthHelper($partner, $language, $langConfig),
        );

        // Generate package manifest
        $this->generatePackageManifest($outputPath, $partner, $language, $langConfig);

        Log::info('SDK generated for partner', [
            'partner_id' => $partner->id,
            'language'   => $language,
            'path'       => $outputPath,
        ]);

        return [
            'success'  => true,
            'message'  => "SDK generated successfully for {$langConfig['name']}",
            'path'     => $outputPath,
            'language' => $language,
        ];
    }


    /**
     * Generate an SDK from the OpenAPI spec (artisan command entry point).
     *
     * Parses the spec to extract endpoint names grouped by tag, and produces
     * typed client stubs with method names and paths for the given language.
     *
     * @return array{success: bool, message: string, files: array<string>}
     */
    public function generateFromSpec(string $language, string $specPath, string $outputPath): array
    {
        $languages = $this->getAvailableLanguages();
        $langConfig = $languages[$language] ?? null;

        if ($langConfig === null) {
            return ['success' => false, 'message' => "Unsupported language: {$language}", 'files' => []];
        }

        $specContent = File::get($specPath);
        $spec = json_decode($specContent, true);

        if (! is_array($spec) || empty($spec['paths'])) {
            return ['success' => false, 'message' => 'Invalid or empty OpenAPI spec.', 'files' => []];
        }

        $langDir = "{$outputPath}/{$language}";
        File::ensureDirectoryExists($langDir);

        $ext = $langConfig['extension'] ?? $language;
        $endpoints = $this->extractEndpoints($spec);
        $files = [];

        // Generate client with endpoint stubs
        $clientFile = "{$langDir}/FinAegisClient.{$ext}";
        File::put($clientFile, $this->generateSpecClient($language, $langConfig, $endpoints));
        $files[] = $clientFile;

        // Generate typed models from schemas
        $modelsFile = "{$langDir}/Models.{$ext}";
        File::put($modelsFile, $this->generateSpecModels($language, $langConfig, $spec));
        $files[] = $modelsFile;

        // Generate README
        $readmeFile = "{$langDir}/README.md";
        File::put($readmeFile, $this->generateSpecReadme($language, $langConfig, $endpoints));
        $files[] = $readmeFile;

        Log::info('SDK generated from spec', ['language' => $language, 'endpoint_count' => count($endpoints), 'path' => $langDir]);

        return [
            'success' => true,
            'message' => "SDK generated successfully for {$langConfig['name']} (" . count($endpoints) . ' endpoints)',
            'files'   => $files,
        ];
    }

    /**
     * Extract endpoints grouped by tag from the OpenAPI spec.
     *
     * @return array<array{tag: string, method: string, path: string, operationId: string, summary: string}>
     */
    private function extractEndpoints(array $spec): array
    {
        $endpoints = [];

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $httpMethod => $operation) {
                if (! is_array($operation) || in_array($httpMethod, ['parameters', 'servers', 'summary', 'description'], true)) {
                    continue;
                }

                $tag = $operation['tags'][0] ?? 'General';
                $operationId = $operation['operationId'] ?? $this->pathToMethodName($httpMethod, $path);
                $summary = $operation['summary'] ?? '';

                $endpoints[] = [
                    'tag'         => $tag,
                    'method'      => strtoupper($httpMethod),
                    'path'        => $path,
                    'operationId' => $operationId,
                    'summary'     => $summary,
                ];
            }
        }

        return $endpoints;
    }

    /**
     * Generate a client class with method stubs for all endpoints.
     *
     * @param  array<string, string>                                                                        $langConfig
     * @param  array<array{tag: string, method: string, path: string, operationId: string, summary: string}>  $endpoints
     */
    private function generateSpecClient(string $language, array $langConfig, array $endpoints): string
    {
        $baseUrl = config('app.url', 'https://api.finaegis.com') . '/api';
        $methodStubs = '';

        $grouped = [];
        foreach ($endpoints as $ep) {
            $grouped[$ep['tag']][] = $ep;
        }

        foreach ($grouped as $tag => $eps) {
            $methodStubs .= $this->generateSectionComment($language, $tag);
            foreach ($eps as $ep) {
                $methodStubs .= $this->generateMethodStub($language, $ep);
            }
        }

        return match ($language) {
            'typescript' => $this->wrapTypescriptClient($baseUrl, $methodStubs),
            'python'     => $this->wrapPythonClient($baseUrl, $methodStubs),
            default      => $this->wrapGenericClient($language, $baseUrl, $methodStubs),
        };
    }

    /**
     * Generate typed model classes from OpenAPI schemas.
     *
     * @param  array<string, string>  $langConfig
     */
    private function generateSpecModels(string $language, array $langConfig, array $spec): string
    {
        $schemas = $spec['components']['schemas'] ?? [];
        $models = '';

        foreach ($schemas as $name => $schema) {
            if (! is_array($schema) || empty($schema['properties'])) {
                continue;
            }

            $models .= $this->generateModelStub($language, $name, $schema);
        }

        if ($models === '') {
            $models = $this->generateSectionComment($language, 'No schemas found in spec');
        }

        return $models;
    }

    /**
     * Generate README for spec-based SDK.
     *
     * @param  array<string, string>                                                                        $langConfig
     * @param  array<array{tag: string, method: string, path: string, operationId: string, summary: string}>  $endpoints
     */
    private function generateSpecReadme(string $language, array $langConfig, array $endpoints): string
    {
        $name = $langConfig['name'] ?? ucfirst($language);
        $packageManager = $langConfig['package_manager'] ?? 'n/a';

        $grouped = [];
        foreach ($endpoints as $ep) {
            $grouped[$ep['tag']][] = $ep;
        }

        $endpointList = '';
        foreach ($grouped as $tag => $eps) {
            $endpointList .= "\n### {$tag}\n\n";
            foreach ($eps as $ep) {
                $endpointList .= "- `{$ep['method']} {$ep['path']}` — {$ep['operationId']}";
                if ($ep['summary']) {
                    $endpointList .= " — {$ep['summary']}";
                }
                $endpointList .= "\n";
            }
        }

        return <<<README
# FinAegis {$name} SDK

Auto-generated SDK from OpenAPI specification.

## Installation

```
{$packageManager} install finaegis-sdk
```

## Endpoints ({$this->countEndpoints($endpoints)})

{$endpointList}

## Authentication

Use your Partner Client ID and Secret:
- Header: `X-Partner-Client-Id`
- Header: `X-Partner-Client-Secret`

Or use Bearer token authentication via the `Authorization` header.
README;
    }

    private function pathToMethodName(string $method, string $path): string
    {
        $clean = str_replace(['/api/', '/v1/', '/v2/', '{', '}'], ['', '', '', '', ''], $path);
        $parts = array_filter(explode('/', $clean));
        $camelCase = lcfirst(str_replace(' ', '', ucwords(implode(' ', $parts))));

        return lcfirst($method) . ucfirst($camelCase);
    }

    /** @param array<array{tag: string, method: string, path: string, operationId: string, summary: string}> $endpoints */
    private function countEndpoints(array $endpoints): int
    {
        return count($endpoints);
    }

    private function generateSectionComment(string $language, string $section): string
    {
        return match ($language) {
            'python' => "\n    # --- {$section} ---\n",
            default  => "\n  // --- {$section} ---\n",
        };
    }

    /** @param array{tag: string, method: string, path: string, operationId: string, summary: string} $ep */
    private function generateMethodStub(string $language, array $ep): string
    {
        $name = lcfirst(str_replace(['-', '_', '.'], '', ucwords($ep['operationId'], '-_.')));
        $comment = $ep['summary'] ?: "{$ep['method']} {$ep['path']}";

        return match ($language) {
            'typescript' => "  /** {$comment} */\n  async {$name}(params?: Record<string, any>): Promise<any> { return this.request('{$ep['method']}', '{$ep['path']}', params); }\n",
            'python'     => "    def {$this->toSnakeCase($name)}(self, **kwargs) -> dict:\n        \"\"\"{$comment}\"\"\"\n        return self._request('{$ep['method']}', '{$ep['path']}', **kwargs)\n\n",
            default      => "  // {$comment}\n  // {$ep['method']} {$ep['path']}\n",
        };
    }

    private function generateModelStub(string $language, string $name, array $schema): string
    {
        $props = $schema['properties'] ?? [];
        $fields = '';

        foreach ($props as $propName => $propSchema) {
            $type = $propSchema['type'] ?? 'any';
            $fields .= match ($language) {
                'typescript' => "  {$propName}?: {$this->tsType($type)};\n",
                'python'     => "    {$this->toSnakeCase($propName)}: {$this->pyType($type)} = None\n",
                default      => "  // {$propName}: {$type}\n",
            };
        }

        return match ($language) {
            'typescript' => "\nexport interface {$name} {\n{$fields}}\n",
            'python'     => "\n@dataclass\nclass {$name}:\n{$fields}\n",
            default      => "\n// Model: {$name}\n{$fields}\n",
        };
    }

    private function tsType(string $oaType): string
    {
        return match ($oaType) {
            'integer', 'number' => 'number',
            'boolean'           => 'boolean',
            'array'             => 'any[]',
            'object'            => 'Record<string, any>',
            default             => 'string',
        };
    }

    private function pyType(string $oaType): string
    {
        return match ($oaType) {
            'integer'           => 'int',
            'number'            => 'float',
            'boolean'           => 'bool',
            'array'             => 'list',
            'object'            => 'dict',
            default             => 'str',
        };
    }

    private function toSnakeCase(string $input): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    private function wrapTypescriptClient(string $baseUrl, string $methods): string
    {
        return <<<TS
/**
 * FinAegis API Client — auto-generated from OpenAPI spec
 */
export class FinAegisClient {
  private baseUrl: string = '{$baseUrl}';
  private headers: Record<string, string>;

  constructor(token: string) {
    this.headers = {
      'Content-Type': 'application/json',
      'Authorization': `Bearer \${token}`,
    };
  }

  private async request(method: string, path: string, body?: any): Promise<any> {
    const response = await fetch(`\${this.baseUrl}\${path}`, {
      method, headers: this.headers,
      body: body ? JSON.stringify(body) : undefined,
    });
    return response.json();
  }
{$methods}
}
TS;
    }

    private function wrapPythonClient(string $baseUrl, string $methods): string
    {
        return <<<PYTHON
\"\"\"
FinAegis API Client — auto-generated from OpenAPI spec
\"\"\"
import requests
from dataclasses import dataclass
from typing import Optional


class FinAegisClient:
    def __init__(self, token: str):
        self.base_url = '{$baseUrl}'
        self.headers = {
            'Content-Type': 'application/json',
            'Authorization': f'Bearer {token}',
        }

    def _request(self, method: str, path: str, **kwargs) -> dict:
        resp = requests.request(method, f'{self.base_url}{path}', headers=self.headers, **kwargs)
        return resp.json()
{$methods}
PYTHON;
    }

    private function wrapGenericClient(string $language, string $baseUrl, string $methods): string
    {
        return "// FinAegis API Client — auto-generated from OpenAPI spec\n// Language: {$language}\n// Base URL: {$baseUrl}\n{$methods}";
    }

    /**
     * Get available SDK languages from config.
     *
     * @return array<string, array<string, string>>
     */
    public function getAvailableLanguages(): array
    {
        return config('baas.sdk.supported_languages', []);
    }

    /**
     * Get SDK status for a partner and language.
     *
     * @return array{exists: bool, language: string, path: string|null, version: string|null}
     */
    public function getSdkStatus(FinancialInstitutionPartner $partner, string $language): array
    {
        $partnerCode = $partner->partner_code;
        $outputPath = config('baas.sdk.output_path') . "/{$partnerCode}/{$language}";
        $exists = File::isDirectory($outputPath);

        return [
            'exists'   => $exists,
            'language' => $language,
            'path'     => $exists ? $outputPath : null,
            'version'  => $exists ? config('baas.sdk.api_version', 'v1') : null,
        ];
    }

    /**
     * Get the OpenAPI spec content.
     */
    public function getOpenApiSpec(): ?string
    {
        $specPath = storage_path('api-docs/api-docs.json');

        if (! File::exists($specPath)) {
            return null;
        }

        return File::get($specPath);
    }

    /**
     * Generate README content.
     *
     * @param  array<string, string>  $langConfig
     */
    private function generateReadme(FinancialInstitutionPartner $partner, string $language, array $langConfig): string
    {
        $name = $langConfig['name'] ?? ucfirst($language);
        $packageManager = $langConfig['package_manager'] ?? 'n/a';
        $partnerName = $partner->institution_name;

        return <<<README
# FinAegis {$name} SDK

Auto-generated SDK for **{$partnerName}** ({$partner->partner_code}).

## Installation

```
{$packageManager} install finaegis-sdk
```

## Quick Start

```{$language}
// Initialize the client
client = new FinAegisClient("{$partner->api_client_id}", "YOUR_SECRET");

// Make API calls
accounts = client.getAccounts();
```

## API Version

{$langConfig['name']} SDK for FinAegis API v{$this->getApiVersion()}

## Support

Contact: support@finaegis.com
README;
    }

    /**
     * Generate client class content.
     *
     * @param  array<string, string>  $langConfig
     */
    private function generateClientClass(
        FinancialInstitutionPartner $partner,
        string $language,
        array $langConfig,
        string $apiVersion,
    ): string {
        $baseUrl = config('app.url', 'https://api.finaegis.com') . "/api/partner/{$apiVersion}";

        return match ($language) {
            'typescript' => $this->generateTypescriptClient($partner, $baseUrl),
            'python'     => $this->generatePythonClient($partner, $baseUrl),
            default      => $this->generateGenericClient($partner, $language, $baseUrl),
        };
    }

    private function generateTypescriptClient(FinancialInstitutionPartner $partner, string $baseUrl): string
    {
        return <<<TS
/**
 * FinAegis API Client
 * Auto-generated for {$partner->institution_name}
 */
export class FinAegisClient {
  private baseUrl: string = '{$baseUrl}';
  private clientId: string;
  private clientSecret: string;

  constructor(clientId: string, clientSecret: string) {
    this.clientId = clientId;
    this.clientSecret = clientSecret;
  }

  private async request(method: string, path: string, body?: any): Promise<any> {
    const response = await fetch(`\${this.baseUrl}\${path}`, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-Partner-Client-Id': this.clientId,
        'X-Partner-Client-Secret': this.clientSecret,
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    return response.json();
  }

  async getProfile(): Promise<any> { return this.request('GET', '/profile'); }
  async getUsage(): Promise<any> { return this.request('GET', '/usage'); }
}
TS;
    }

    private function generatePythonClient(FinancialInstitutionPartner $partner, string $baseUrl): string
    {
        return <<<PYTHON
\"\"\"
FinAegis API Client
Auto-generated for {$partner->institution_name}
\"\"\"
import requests


class FinAegisClient:
    def __init__(self, client_id: str, client_secret: str):
        self.base_url = '{$baseUrl}'
        self.client_id = client_id
        self.client_secret = client_secret

    def _headers(self) -> dict:
        return {
            'Content-Type': 'application/json',
            'X-Partner-Client-Id': self.client_id,
            'X-Partner-Client-Secret': self.client_secret,
        }

    def _request(self, method: str, path: str, **kwargs) -> dict:
        resp = requests.request(method, f'{self.base_url}{path}', headers=self._headers(), **kwargs)
        return resp.json()

    def get_profile(self) -> dict:
        return self._request('GET', '/profile')

    def get_usage(self) -> dict:
        return self._request('GET', '/usage')
PYTHON;
    }

    private function generateGenericClient(FinancialInstitutionPartner $partner, string $language, string $baseUrl): string
    {
        return "// FinAegis API Client for {$partner->institution_name}\n// Language: {$language}\n// Base URL: {$baseUrl}\n// Configure with your Client ID and Secret\n";
    }

    /**
     * Generate auth helper content.
     *
     * @param  array<string, string>  $langConfig
     */
    private function generateAuthHelper(
        FinancialInstitutionPartner $partner,
        string $language,
        array $langConfig,
    ): string {
        return "// FinAegis Auth Helper\n// Partner: {$partner->partner_code}\n// Use X-Partner-Client-Id and X-Partner-Client-Secret headers for authentication.\n";
    }

    /**
     * Generate package manifest (package.json, setup.py, etc.).
     *
     * @param  array<string, string>  $langConfig
     */
    private function generatePackageManifest(
        string $outputPath,
        FinancialInstitutionPartner $partner,
        string $language,
        array $langConfig,
    ): void {
        $manifest = match ($language) {
            'typescript' => [
                'file'    => 'package.json',
                'content' => json_encode([
                    'name'        => '@finaegis/sdk',
                    'version'     => '1.0.0',
                    'description' => "FinAegis SDK for {$partner->institution_name}",
                    'main'        => 'FinAegisClient.ts',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ],
            'python' => [
                'file'    => 'setup.py',
                'content' => "from setuptools import setup\nsetup(name='finaegis-sdk', version='1.0.0', description='FinAegis SDK for {$partner->institution_name}')\n",
            ],
            default => [
                'file'    => 'manifest.json',
                'content' => json_encode([
                    'name'     => 'finaegis-sdk',
                    'version'  => '1.0.0',
                    'language' => $language,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ],
        };

        File::put("{$outputPath}/{$manifest['file']}", (string) $manifest['content']);
    }

    private function getApiVersion(): string
    {
        return config('baas.sdk.api_version', 'v1');
    }
}
