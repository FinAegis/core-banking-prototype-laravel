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
 * zelta pay status <paymentId>.
 */
class StatusCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('pay:status');
        $this->setDescription('Check payment status');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paymentId', InputArgument::REQUIRED, 'Payment ID to check')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        if (! $this->ensureAuthenticated($auth)) {
            return 2;
        }

        /** @var string $paymentId */
        $paymentId = $input->getArgument('paymentId');

        $api = new ApiClient($auth);
        $result = $api->get("/v1/x402/payments/{$paymentId}");

        if ($result['status'] !== 200) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? 'Failed to fetch payment status');

            return Command::FAILURE;
        }

        $formatter->output($result['body']['data'] ?? $result['body'], forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
