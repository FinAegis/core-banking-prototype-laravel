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
 * zelta wallet send --to <address> --amount <n> --token USDC --network eip155:8453 [--yes].
 */
class SendCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('wallet:send');
        $this->setDescription('Send tokens from your wallet');
    }

    protected function configure(): void
    {
        $this
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Recipient address')
            ->addOption('amount', null, InputOption::VALUE_REQUIRED, 'Amount to send')
            ->addOption('token', null, InputOption::VALUE_OPTIONAL, 'Token symbol', 'USDC')
            ->addOption('network', 'n', InputOption::VALUE_OPTIONAL, 'Network (e.g., eip155:8453)', 'eip155:8453')
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

        /** @var string|null $to */
        $to = $input->getOption('to');
        /** @var string|null $amount */
        $amount = $input->getOption('amount');

        if ($to === null || $amount === null) {
            $formatter->error('VALIDATION', 'Both --to and --amount are required');

            return 4;
        }

        /** @var string $token */
        $token = $input->getOption('token') ?? 'USDC';
        /** @var string $network */
        $network = $input->getOption('network') ?? 'eip155:8453';

        // Confirm before sending unless --yes
        if (! $input->getOption('yes')) {
            $output->writeln("Transfer {$amount} {$token} to {$to} on {$network}");
            $output->writeln('Use --yes to skip this confirmation.');

            return Command::SUCCESS;
        }

        $api = new ApiClient($auth);
        $result = $api->post('/v1/wallet/transfers', [
            'to'      => $to,
            'amount'  => $amount,
            'token'   => $token,
            'network' => $network,
        ]);

        if ($result['status'] >= 400) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? "HTTP {$result['status']}");

            return Command::FAILURE;
        }

        $formatter->output($result['body']['data'] ?? $result['body'], forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
