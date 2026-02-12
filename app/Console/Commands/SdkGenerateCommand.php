<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\FinancialInstitution\Services\SdkGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SdkGenerateCommand extends Command
{
    protected $signature = 'sdk:generate
        {language : The target language (typescript, python, java, go, csharp, php)}
        {--output= : Output directory (default: storage/app/sdk)}
        {--spec= : Path to OpenAPI spec file (default: storage/api-docs/api-docs.json)}';

    protected $description = 'Generate an SDK package from the OpenAPI specification';

    public function handle(SdkGeneratorService $service): int
    {
        $language = $this->argument('language');
        $languages = $service->getAvailableLanguages();

        if (! isset($languages[$language])) {
            $this->error("Unsupported language: {$language}");
            $this->info('Available languages: ' . implode(', ', array_keys($languages)));

            return self::FAILURE;
        }

        $specPath = $this->option('spec') ?? storage_path('api-docs/api-docs.json');
        $outputPath = $this->option('output') ?? storage_path('app/sdk');

        if (! File::exists($specPath)) {
            $this->error("OpenAPI spec not found at: {$specPath}");
            $this->info('Run `php artisan l5-swagger:generate` first to generate the spec.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists($outputPath);

        $this->info("Generating {$languages[$language]['name']} SDK from {$specPath}...");

        $result = $service->generateFromSpec($language, $specPath, $outputPath);

        if (! $result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);

        if (! empty($result['files'])) {
            $this->newLine();
            $this->info('Generated files:');
            foreach ($result['files'] as $file) {
                $this->line("  - {$file}");
            }
        }

        return self::SUCCESS;
    }
}
