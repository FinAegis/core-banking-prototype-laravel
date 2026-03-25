<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Auth;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta auth login [--key zk_xxx] [--profile production].
 */
class LoginCommand extends Command
{
    public function __construct()
    {
        parent::__construct('auth:login');
        $this->setDescription('Authenticate with the Zelta API');
    }

    protected function configure(): void
    {
        $this
            ->addOption('key', 'k', InputOption::VALUE_REQUIRED, 'API key (e.g., zk_live_xxx)')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Profile name', 'default')
            ->addOption('url', null, InputOption::VALUE_OPTIONAL, 'API base URL', 'https://api.zelta.app');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        /** @var string|null $apiKey */
        $apiKey = $input->getOption('key');
        /** @var string $profile */
        $profile = $input->getOption('profile') ?? 'default';
        /** @var string $baseUrl */
        $baseUrl = $input->getOption('url') ?? 'https://api.zelta.app';

        if ($apiKey === null) {
            $formatter->error('MISSING_KEY', 'API key required. Usage: zelta auth login --key zk_live_xxx');

            return Command::FAILURE;
        }

        $auth->login($apiKey, $profile, $baseUrl);
        $formatter->success("Authenticated as profile '{$profile}' at {$baseUrl}");

        return Command::SUCCESS;
    }
}
