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
 * zelta auth logout [--profile <name>].
 */
class LogoutCommand extends Command
{
    public function __construct()
    {
        parent::__construct('auth:logout');
        $this->setDescription('Log out and clear credentials');
    }

    protected function configure(): void
    {
        $this
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Profile to log out', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        /** @var string $profile */
        $profile = $input->getOption('profile') ?? 'default';

        if (! $auth->isAuthenticated($profile)) {
            $formatter->error('NOT_AUTHENTICATED', "Profile '{$profile}' is not authenticated.");

            return 2;
        }

        $auth->logout($profile);
        $formatter->success("Logged out of profile '{$profile}'.");

        return Command::SUCCESS;
    }
}
