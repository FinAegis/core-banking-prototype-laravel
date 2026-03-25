<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Sms;

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
 * zelta sms send --to +370xxx --message "Hello".
 */
class SendCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('sms:send');
        $this->setDescription('Send an SMS via VertexSMS');
    }

    protected function configure(): void
    {
        $this
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Recipient phone number (E.164)')
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Message text')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Sender ID', 'Zelta')
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
        /** @var string|null $message */
        $message = $input->getOption('message');

        if ($to === null || $message === null) {
            $formatter->error('VALIDATION', 'Both --to and --message are required');

            return 4; // validation error
        }

        $api = new ApiClient($auth);
        $result = $api->post('/v1/sms/send', [
            'to'      => $to,
            'message' => $message,
            'from'    => $input->getOption('from'),
        ]);

        if ($result['status'] === 402) {
            $formatter->error('PAYMENT_REQUIRED', 'SMS sending requires payment. Ensure spending limits are configured.');

            return 3; // payment error
        }

        if ($result['status'] >= 400) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? "HTTP {$result['status']}");

            return Command::FAILURE;
        }

        $formatter->output($result['body']['data'] ?? $result['body'], forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
