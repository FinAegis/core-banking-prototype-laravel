<?php

declare(strict_types=1);

namespace ZeltaCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Concerns\RequiresAuth;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta whoami.
 */
class WhoamiCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('whoami');
        $this->setDescription('Show authenticated user info');
    }

    protected function configure(): void
    {
        $this->configureJsonOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        if (! $this->ensureAuthenticated($auth)) {
            return 2;
        }

        $formatter->output([
            'profile'  => $auth->getActiveProfile(),
            'base_url' => $auth->getBaseUrl(),
            'api_key'  => substr($auth->getApiKey() ?? '', 0, 10) . '...',
        ], forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
