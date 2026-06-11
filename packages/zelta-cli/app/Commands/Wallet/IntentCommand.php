<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Wallet;

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
 * zelta wallet:intent <intentId>.
 *
 * Look up the status of a send intent created by a device (prepare/submit
 * flow). Read-only counterpart to the non-custodial mobile send.
 */
class IntentCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('wallet:intent');
        $this->setDescription('Show the status of a wallet send intent');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('intentId', InputArgument::REQUIRED, 'Send intent identifier (public id from prepare)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        if (! $this->ensureAuthenticated($auth, $output)) {
            return 2;
        }

        /** @var string $intentId */
        $intentId = $input->getArgument('intentId');

        $api = new ApiClient($auth);
        $result = $api->get('/v1/wallet/transactions/by-intent/' . rawurlencode($intentId));

        if ($result['status'] === 404) {
            $formatter->error('INTENT_NOT_FOUND', $result['body']['error']['message'] ?? 'No send matches this id.');

            return Command::FAILURE;
        }

        if ($result['status'] !== 200) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? "HTTP {$result['status']}");

            return Command::FAILURE;
        }

        /** @var array<string, mixed> $intent */
        $intent = $result['body']['data'] ?? [];

        if ($this->shouldOutputJson($input)) {
            $formatter->json($intent);

            return Command::SUCCESS;
        }

        $rows = [];
        foreach (['intent_id', 'status', 'network', 'asset', 'amount', 'tx_hash', 'explorer_url', 'error_code', 'error_message', 'created_at', 'submitted_at', 'confirmed_at', 'failed_at'] as $field) {
            $value = $intent[$field] ?? null;
            if ($value !== null && $value !== '') {
                $rows[] = [$field, (string) $value];
            }
        }

        $formatter->table(['Field', 'Value'], $rows);

        return Command::SUCCESS;
    }
}
