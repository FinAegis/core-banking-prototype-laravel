<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Limits;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Concerns\RequiresAuth;
use ZeltaCli\Services\ApiClient;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta limits list.
 */
class ListCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('limits:list');
        $this->setDescription('List spending limits');
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
        $result = $api->get('/v1/x402/spending-limits');

        if ($result['status'] !== 200) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? 'Failed to fetch spending limits');

            return Command::FAILURE;
        }

        $limits = $result['body']['data'] ?? [];

        if ($this->shouldOutputJson($input)) {
            $formatter->json($limits);
        } else {
            if ($limits === []) {
                $formatter->success('No spending limits configured.');

                return Command::SUCCESS;
            }

            $rows = array_map(fn (array $l) => [
                $l['agent_id'] ?? '',
                $l['daily_limit'] ?? '-',
                $l['per_tx_limit'] ?? '-',
                ($l['auto_pay'] ?? false) ? 'yes' : 'no',
                $l['created_at'] ?? '',
            ], $limits);

            $formatter->table(['Agent ID', 'Daily Limit', 'Per-TX Limit', 'Auto-Pay', 'Created'], $rows);
        }

        return Command::SUCCESS;
    }
}
