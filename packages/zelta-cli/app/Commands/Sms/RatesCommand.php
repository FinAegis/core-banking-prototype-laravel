<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Sms;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Services\ApiClient;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta sms rates [--country LT].
 */
class RatesCommand extends Command
{
    use HasJsonOutput;

    public function __construct()
    {
        parent::__construct('sms:rates');
        $this->setDescription('View SMS rates by country');
    }

    protected function configure(): void
    {
        $this
            ->addOption('country', 'c', InputOption::VALUE_OPTIONAL, 'Country code (e.g., LT)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();
        $api = new ApiClient($auth);

        $query = array_filter(['country' => $input->getOption('country')]);
        $result = $api->get('/v1/sms/rates', $query);

        if ($result['status'] !== 200) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? 'Failed to fetch rates');

            return Command::FAILURE;
        }

        $formatter->output($result['body']['data'] ?? $result['body'], forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
