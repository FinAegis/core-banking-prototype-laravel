<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Auth;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta auth status.
 */
class StatusCommand extends Command
{
    use HasJsonOutput;

    public function __construct()
    {
        parent::__construct('auth:status');
        $this->setDescription('Show authentication status and active profile');
    }

    protected function configure(): void
    {
        $this->configureJsonOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        $activeProfile = $auth->getActiveProfile();
        $profiles = $auth->listProfiles();

        if ($profiles === []) {
            $formatter->error('NOT_AUTHENTICATED', 'No profiles found. Run: zelta auth login --key <your-api-key>');

            return 2; // auth error exit code
        }

        $data = [
            'active_profile' => $activeProfile,
            'authenticated'  => $auth->isAuthenticated(),
            'base_url'       => $auth->getBaseUrl(),
            'profiles'       => array_keys($profiles),
        ];

        $formatter->output($data, forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
