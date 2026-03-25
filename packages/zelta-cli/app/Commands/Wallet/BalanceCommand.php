<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Wallet;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Concerns\RequiresAuth;
use ZeltaCli\Services\ApiClient;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta wallet balance.
 */
class BalanceCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('wallet:balance');
        $this->setDescription('Show wallet balances');
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

        $api = new ApiClient($auth);
        $result = $api->get('/v1/wallet/balances');

        if ($result['status'] !== 200) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? 'Failed to fetch balances');

            return Command::FAILURE;
        }

        $balances = $result['body']['data'] ?? [];

        if ($this->shouldOutputJson($input)) {
            $formatter->json($balances);
        } else {
            if ($balances === []) {
                $formatter->success('No balances found.');

                return Command::SUCCESS;
            }

            $rows = array_map(fn (array $b) => [
                $b['token'] ?? '',
                $b['balance'] ?? '0',
                $b['network'] ?? '',
                $b['address'] ?? '',
            ], $balances);

            $formatter->table(['Token', 'Balance', 'Network', 'Address'], $rows);
        }

        return Command::SUCCESS;
    }
}
