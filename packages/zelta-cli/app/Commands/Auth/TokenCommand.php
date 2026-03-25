<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Auth;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Concerns\RequiresAuth;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta auth token [--unmask].
 */
class TokenCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('auth:token');
        $this->setDescription('Show the current API token');
    }

    protected function configure(): void
    {
        $this
            ->addOption('unmask', null, InputOption::VALUE_NONE, 'Show the full unmasked token')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        if (! $this->ensureAuthenticated($auth)) {
            return 2;
        }

        $apiKey = $auth->getApiKey() ?? '';
        $masked = $input->getOption('unmask')
            ? $apiKey
            : substr($apiKey, 0, 10) . str_repeat('*', max(0, strlen($apiKey) - 10));

        $data = [
            'profile' => $auth->getActiveProfile(),
            'token'   => $masked,
        ];

        $formatter->output($data, forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
