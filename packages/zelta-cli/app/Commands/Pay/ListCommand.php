<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Pay;

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
 * zelta pay list [--status settled] [--network eip155:8453].
 */
class ListCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('pay:list');
        $this->setDescription('List recent x402 payments');
    }

    protected function configure(): void
    {
        $this
            ->addOption('status', 's', InputOption::VALUE_OPTIONAL, 'Filter by status (pending, settled, failed)')
            ->addOption('network', 'n', InputOption::VALUE_OPTIONAL, 'Filter by network (e.g., eip155:8453)')
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
        $query = array_filter([
            'status'  => $input->getOption('status'),
            'network' => $input->getOption('network'),
        ]);

        $result = $api->get('/v1/x402/payments', $query);

        if ($result['status'] !== 200) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? 'Failed to fetch payments');

            return Command::FAILURE;
        }

        $payments = $result['body']['data'] ?? [];

        if ($this->shouldOutputJson($input)) {
            $formatter->json($payments);
        } else {
            if ($payments === []) {
                $formatter->success('No payments found.');

                return Command::SUCCESS;
            }

            $rows = array_map(fn (array $p) => [
                $p['id'] ?? '',
                $p['status'] ?? '',
                $p['amount'] ?? '',
                $p['network'] ?? '',
                $p['endpoint_path'] ?? '',
                $p['created_at'] ?? '',
            ], $payments);

            $formatter->table(
                ['ID', 'Status', 'Amount', 'Network', 'Endpoint', 'Created'],
                $rows,
            );
        }

        return Command::SUCCESS;
    }
}
