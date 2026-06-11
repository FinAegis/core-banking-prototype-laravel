<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Wallet;

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
 * zelta wallet:transactions [--limit 20].
 *
 * Read-only activity feed. Sending is non-custodial (the device holding
 * the Privy key signs every transaction), so the CLI cannot send — it can
 * only inspect wallet activity.
 */
class TransactionsCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('wallet:transactions');
        $this->setDescription('List recent wallet transactions (activity feed)');
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Number of transactions to fetch (max 50)', '20')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        if (! $this->ensureAuthenticated($auth, $output)) {
            return 2;
        }

        $limit = (int) ($input->getOption('limit') ?? '20');
        if ($limit < 1 || $limit > 50) {
            $formatter->error('VALIDATION', '--limit must be between 1 and 50');

            return 4;
        }

        $api = new ApiClient($auth);
        $result = $api->get('/v1/wallet/transactions', ['limit' => $limit]);

        if ($result['status'] !== 200) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? 'Failed to fetch transactions');

            return Command::FAILURE;
        }

        /** @var array{items?: list<array<string, mixed>>, next_cursor?: string|null, has_more?: bool} $feed */
        $feed = $result['body']['data'] ?? [];
        $items = $feed['items'] ?? [];

        if ($this->shouldOutputJson($input)) {
            $formatter->json($feed);

            return Command::SUCCESS;
        }

        if ($items === []) {
            $formatter->success('No transactions found.');

            return Command::SUCCESS;
        }

        $rows = array_map(fn (array $t) => [
            $t['id'] ?? '',
            $t['type'] ?? '',
            $t['amount'] ?? '',
            $t['asset'] ?? '',
            $t['status'] ?? '',
            $t['timestamp'] ?? '',
        ], $items);

        $formatter->table(['ID', 'Type', 'Amount', 'Asset', 'Status', 'Timestamp'], $rows);

        if (($feed['has_more'] ?? false) === true) {
            $output->writeln('<comment>More transactions available — increase --limit.</comment>');
        }

        return Command::SUCCESS;
    }
}
