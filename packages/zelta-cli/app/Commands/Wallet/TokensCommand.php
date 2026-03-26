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
 * zelta wallet tokens.
 */
class TokensCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('wallet:tokens');
        $this->setDescription('List available tokens and their addresses');
    }

    protected function configure(): void
    {
        $this->configureJsonOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        if (! $this->ensureAuthenticated($auth, $output)) {
            return 2;
        }

        $api = new ApiClient($auth);
        $result = $api->get('/v1/wallet/tokens');

        if ($result['status'] !== 200) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? 'Failed to fetch tokens');

            return Command::FAILURE;
        }

        $tokens = $result['body']['data'] ?? [];

        if ($this->shouldOutputJson($input)) {
            $formatter->json($tokens);
        } else {
            if ($tokens === []) {
                $formatter->success('No tokens found.');

                return Command::SUCCESS;
            }

            $rows = array_map(fn (array $t) => [
                $t['symbol'] ?? '',
                $t['name'] ?? '',
                $t['address'] ?? '',
                $t['network'] ?? '',
                (string) ($t['decimals'] ?? ''),
            ], $tokens);

            $formatter->table(['Symbol', 'Name', 'Address', 'Network', 'Decimals'], $rows);
        }

        return Command::SUCCESS;
    }
}
