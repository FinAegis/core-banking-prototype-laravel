<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Sdk;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Concerns\RequiresAuth;
use ZeltaCli\Services\ApiClient;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta sdk generate <language> [--output <dir>].
 */
class GenerateCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    private const SUPPORTED_LANGUAGES = ['typescript', 'python', 'java', 'go', 'csharp', 'php'];

    public function __construct()
    {
        parent::__construct('sdk:generate');
        $this->setDescription('Generate a typed SDK for the Zelta API');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('language', InputArgument::REQUIRED, 'Target language: ' . implode(', ', self::SUPPORTED_LANGUAGES))
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output directory', '.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        /** @var string $language */
        $language = $input->getArgument('language');

        if (! in_array($language, self::SUPPORTED_LANGUAGES, true)) {
            $formatter->error('VALIDATION', "Unsupported language '{$language}'. Supported: " . implode(', ', self::SUPPORTED_LANGUAGES));

            return 4;
        }

        /** @var string $outputDir */
        $outputDir = $input->getOption('output') ?? '.';

        // Try local artisan command if available
        $artisanPath = getcwd() . '/artisan';
        if (file_exists($artisanPath)) {
            $output->writeln("<info>Generating {$language} SDK via local artisan...</info>");
            $exitCode = 0;
            passthru("php artisan sdk:generate {$language} --output=" . escapeshellarg($outputDir), $exitCode);

            return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
        }

        // Fallback to API
        if (! $this->ensureAuthenticated($auth)) {
            return 2;
        }

        $api = new ApiClient($auth);
        $result = $api->post('/v1/admin/sdk/generate', [
            'language' => $language,
            'output'   => $outputDir,
        ]);

        if ($result['status'] >= 400) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? "HTTP {$result['status']}");

            return Command::FAILURE;
        }

        $formatter->output($result['body']['data'] ?? $result['body'], forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
