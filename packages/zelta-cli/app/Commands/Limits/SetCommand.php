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
 * zelta limits set <agentId> --daily <amount> --per-tx <amount> [--auto-pay].
 */
class SetCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('limits:set');
        $this->setDescription('Set spending limits for an agent');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('agentId', InputArgument::REQUIRED, 'Agent ID to set limits for')
            ->addOption('daily', null, InputOption::VALUE_OPTIONAL, 'Daily spending limit')
            ->addOption('per-tx', null, InputOption::VALUE_OPTIONAL, 'Per-transaction limit')
            ->addOption('auto-pay', null, InputOption::VALUE_NONE, 'Enable automatic payments')
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

        $payload = array_filter([
            'agent_id'     => $agentId,
            'daily_limit'  => $input->getOption('daily'),
            'per_tx_limit' => $input->getOption('per-tx'),
            'auto_pay'     => $input->getOption('auto-pay') ?: null,
        ]);

        $api = new ApiClient($auth);
        $result = $api->post('/v1/x402/spending-limits', $payload);

        if ($result['status'] >= 400) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? "HTTP {$result['status']}");

            return Command::FAILURE;
        }

        $formatter->output($result['body']['data'] ?? $result['body'], forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
