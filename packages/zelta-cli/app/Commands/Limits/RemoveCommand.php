<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Limits;

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
 * zelta limits remove <agentId> [--yes].
 */
class RemoveCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('limits:remove');
        $this->setDescription('Remove spending limits for an agent');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('agentId', InputArgument::REQUIRED, 'Agent ID to remove limits for')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        if (! $this->ensureAuthenticated($auth)) {
            return 2;
        }

        /** @var string $agentId */
        $agentId = $input->getArgument('agentId');

        if (! $input->getOption('yes')) {
            $output->writeln("Remove spending limits for agent '{$agentId}'?");
            $output->writeln('Use --yes to skip this confirmation.');

            return Command::SUCCESS;
        }

        $api = new ApiClient($auth);
        $result = $api->delete("/v1/x402/spending-limits/{$agentId}");

        if ($result['status'] >= 400) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? "HTTP {$result['status']}");

            return Command::FAILURE;
        }

        $formatter->success("Spending limits removed for agent '{$agentId}'.");

        return Command::SUCCESS;
    }
}
