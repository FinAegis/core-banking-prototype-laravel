<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Pay;

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
 * zelta pay send <url> [--amount <n>] [--network eip155:8453] [--yes].
 */
class SendCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('pay:send');
        $this->setDescription('Pay for an API endpoint via x402');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED, 'Target URL that requires payment')
            ->addOption('amount', null, InputOption::VALUE_OPTIONAL, 'Payment amount override')
            ->addOption('network', 'n', InputOption::VALUE_OPTIONAL, 'Network (e.g., eip155:8453)', 'eip155:8453')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        if (! $this->ensureAuthenticated($auth, $output)) {
            return 2;
        }

        /** @var string $url */
        $url = $input->getArgument('url');
        /** @var string $network */
        $network = $input->getOption('network') ?? 'eip155:8453';

        $api = new ApiClient($auth);

        // First attempt — may return 402
        $result = $api->get($url);

        if ($result['status'] === 402) {
            $paymentInfo = $result['body'];
            $amount = $input->getOption('amount') ?? ($paymentInfo['amount'] ?? 'unknown');

            if (! $input->getOption('yes')) {
                $output->writeln("Payment required: {$amount} on {$network}");
                $output->writeln('Use --yes to confirm payment.');

                return Command::SUCCESS;
            }

            // Retry with payment
            $payResult = $api->post('/v1/x402/payments', [
                'url'     => $url,
                'amount'  => $amount,
                'network' => $network,
            ]);

            if ($payResult['status'] >= 400) {
                $formatter->error('PAYMENT_FAILED', $payResult['body']['message'] ?? "HTTP {$payResult['status']}");

                return 3;
            }

            $formatter->output($payResult['body']['data'] ?? $payResult['body'], forceJson: $this->shouldOutputJson($input));

            return Command::SUCCESS;
        }

        if ($result['status'] >= 400) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? "HTTP {$result['status']}");

            return Command::FAILURE;
        }

        $formatter->output($result['body']['data'] ?? $result['body'], forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
