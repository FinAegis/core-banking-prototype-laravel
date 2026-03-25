<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Endpoints;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Concerns\RequiresAuth;
use ZeltaCli\Services\ApiClient;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta endpoints list.
 */
class ListCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('endpoints:list');
        $this->setDescription('List monetized API endpoints');
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
        $result = $api->get('/v1/x402/endpoints');

        if ($result['status'] !== 200) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? 'Failed to fetch endpoints');

            return Command::FAILURE;
        }

        $endpoints = $result['body']['data'] ?? [];

        if ($this->shouldOutputJson($input)) {
            $formatter->json($endpoints);
        } else {
            $rows = array_map(fn (array $e) => [
                $e['id'] ?? '',
                $e['method'] ?? '',
                $e['path'] ?? '',
                $e['price'] ?? '',
                ($e['is_active'] ?? false) ? 'active' : 'inactive',
            ], $endpoints);

            $formatter->table(['ID', 'Method', 'Path', 'Price', 'Status'], $rows);
        }

        return Command::SUCCESS;
    }
}
