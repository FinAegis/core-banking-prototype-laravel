<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Agents;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Concerns\RequiresAuth;
use ZeltaCli\Services\ApiClient;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta agents discover [--capability payment].
 */
class DiscoverCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('agents:discover');
        $this->setDescription('Discover available AI agents');
    }

    protected function configure(): void
    {
        $this
            ->addOption('capability', null, InputOption::VALUE_OPTIONAL, 'Filter by capability (e.g., payment)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        if (! $this->ensureAuthenticated($auth)) {
            return 2;
        }

        $api = new ApiClient($auth);
        $query = array_filter(['capability' => $input->getOption('capability')]);
        $result = $api->get('/v1/agent-protocol/agents/discover', $query);

        if ($result['status'] !== 200) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? 'Failed to discover agents');

            return Command::FAILURE;
        }

        $agents = $result['body']['data'] ?? [];

        if ($this->shouldOutputJson($input)) {
            $formatter->json($agents);
        } else {
            if ($agents === []) {
                $formatter->success('No agents found.');

                return Command::SUCCESS;
            }

            $rows = array_map(fn (array $a) => [
                $a['did'] ?? '',
                $a['name'] ?? '',
                implode(', ', $a['capabilities'] ?? []),
                $a['status'] ?? '',
            ], $agents);

            $formatter->table(['DID', 'Name', 'Capabilities', 'Status'], $rows);
        }

        return Command::SUCCESS;
    }
}
