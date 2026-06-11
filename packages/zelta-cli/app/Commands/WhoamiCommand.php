<?php

declare(strict_types=1);

namespace ZeltaCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Concerns\RequiresAuth;
use ZeltaCli\Services\ApiClient;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta whoami.
 *
 * Resolves the authenticated identity server-side (GET /auth/user) so a
 * bad token is reported instead of echoing local credentials blindly.
 */
class WhoamiCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('whoami');
        $this->setDescription('Show authenticated user info (verified against the API)');
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

        $data = [
            'profile'  => $auth->getActiveProfile(),
            'base_url' => $auth->getBaseUrl(),
            'api_key'  => '***' . substr($auth->getApiKey() ?? '', -4),
        ];

        $api = new ApiClient($auth);

        try {
            $result = $api->get('/auth/user');

            if ($result['status'] === 401 || $result['status'] === 403) {
                $formatter->error('INVALID_TOKEN', "The stored token was rejected by {$data['base_url']} (HTTP {$result['status']}). Run: zelta auth login --key <api-token>");

                return 2;
            }

            /** @var array<string, mixed> $userData */
            $userData = $result['body']['data'] ?? [];
            if (is_array($userData)) {
                foreach (['id', 'name', 'email'] as $field) {
                    if (isset($userData[$field]) && is_scalar($userData[$field])) {
                        $data[$field] = $userData[$field];
                    }
                }
            }
        } catch (\Throwable $e) {
            $data['verify_error'] = $e->getMessage();
        }

        $formatter->output($data, forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
