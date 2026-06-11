<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Auth;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Services\ApiClient;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta auth status.
 *
 * Shows the active profile and verifies the stored token against the
 * API (GET /auth/user) so an expired or revoked token is caught here
 * rather than on first use.
 */
class StatusCommand extends Command
{
    use HasJsonOutput;

    public function __construct()
    {
        parent::__construct('auth:status');
        $this->setDescription('Show authentication status and verify the token server-side');
    }

    protected function configure(): void
    {
        $this->configureJsonOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        $activeProfile = $auth->getActiveProfile();
        $profiles = $auth->listProfiles();

        if ($profiles === []) {
            $formatter->error('NOT_AUTHENTICATED', 'No profiles found. Run: zelta auth login --key <api-token>');

            return 2; // auth error exit code
        }

        $data = [
            'active_profile' => $activeProfile,
            'authenticated'  => $auth->isAuthenticated(),
            'base_url'       => $auth->getBaseUrl(),
            'profiles'       => array_keys($profiles),
        ];

        // Server-side verification — catches revoked/expired tokens early.
        if ($auth->isAuthenticated()) {
            $api = new ApiClient($auth);

            try {
                $result = $api->get('/auth/user');
                $data['token_valid'] = $result['status'] === 200;

                /** @var array<string, mixed> $userData */
                $userData = $result['body']['data'] ?? [];
                if (is_array($userData) && isset($userData['email']) && is_string($userData['email'])) {
                    $data['user'] = $userData['email'];
                }

                if ($result['status'] === 401 || $result['status'] === 403) {
                    $formatter->error('INVALID_TOKEN', "The stored token was rejected by {$data['base_url']} (HTTP {$result['status']}). Run: zelta auth login --key <api-token>");

                    return 2;
                }
            } catch (\Throwable $e) {
                $data['token_valid'] = null; // unreachable — could not verify
                $data['verify_error'] = $e->getMessage();
            }
        }

        $formatter->output($data, forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
