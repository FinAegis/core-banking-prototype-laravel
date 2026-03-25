<?php

declare(strict_types=1);

namespace ZeltaCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta config [<key>] [<value>].
 */
class ConfigCommand extends Command
{
    use HasJsonOutput;

    public function __construct()
    {
        parent::__construct('config');
        $this->setDescription('View or update CLI configuration');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::OPTIONAL, 'Configuration key to get or set')
            ->addArgument('value', InputArgument::OPTIONAL, 'Value to set')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $configPath = ($_SERVER['HOME'] ?? '') . '/.zelta/config.json';

        $config = [];
        if (file_exists($configPath)) {
            $contents = file_get_contents($configPath);
            $decoded = json_decode($contents ?: '{}', true);
            $config = is_array($decoded) ? $decoded : [];
        }

        /** @var string|null $key */
        $key = $input->getArgument('key');
        /** @var string|null $value */
        $value = $input->getArgument('value');

        // Set a value
        if ($key !== null && $value !== null) {
            $config[$key] = $value;
            $dir = dirname($configPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $formatter->success("Set {$key} = {$value}");

            return Command::SUCCESS;
        }

        // Get a single value
        if ($key !== null) {
            if (! array_key_exists($key, $config)) {
                $formatter->error('NOT_FOUND', "Configuration key '{$key}' not set.");

                return Command::FAILURE;
            }

            $formatter->output([$key => $config[$key]], forceJson: $this->shouldOutputJson($input));

            return Command::SUCCESS;
        }

        // Show all config
        if ($config === []) {
            $formatter->success('No configuration set. Usage: zelta config <key> <value>');

            return Command::SUCCESS;
        }

        $formatter->output($config, forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
