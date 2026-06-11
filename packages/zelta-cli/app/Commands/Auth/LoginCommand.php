<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Auth;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Services\ApiClient;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta auth login --key <api-token> [--profile production].
 *
 * The token is a Sanctum personal access token created in the Zelta
 * dashboard (Profile -> API Tokens). The token is verified against the
 * API before being stored, so a bad token fails here instead of on
 * first use.
 */
class LoginCommand extends Command
{
    public function __construct()
    {
        parent::__construct('auth:login');
        $this->setDescription('Authenticate with the Zelta API using a personal access token');
        $this->setHelp(
            "Create a personal access token in the Zelta dashboard (Profile -> API Tokens),\n"
            . "then run:\n\n"
            . "  zelta auth login --key <api-token>\n\n"
            . 'The token is verified against the API before being stored in ~/.zelta/credentials.json.'
        );
    }

    protected function configure(): void
    {
        $this
            ->addOption('key', 'k', InputOption::VALUE_REQUIRED, 'Personal access token from the Zelta dashboard (Profile -> API Tokens)')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Profile name', 'default')
            ->addOption('url', null, InputOption::VALUE_OPTIONAL, 'API base URL', 'https://api.zelta.app')
            ->addOption('no-verify', null, InputOption::VALUE_NONE, 'Skip the server-side token check (offline use)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        /** @var string|null $apiKey */
        $apiKey = $input->getOption('key');
        /** @var string $profile */
        $profile = $input->getOption('profile') ?? 'default';
        /** @var string $baseUrl */
        $baseUrl = $input->getOption('url') ?? 'https://api.zelta.app';

        if ($apiKey === null) {
            $formatter->error('MISSING_KEY', 'API token required. Usage: zelta auth login --key <api-token> (create one in the Zelta dashboard under Profile -> API Tokens)');

            return Command::FAILURE;
        }

        $auth->login($apiKey, $profile, $baseUrl);

        if (! $input->getOption('no-verify')) {
            $api = new ApiClient($auth);

            try {
                $result = $api->get('/auth/user');
            } catch (\Throwable $e) {
                $output->writeln("<comment>Warning: could not reach {$baseUrl} to verify the token ({$e->getMessage()}). Credentials stored unverified.</comment>");
                $formatter->success("Authenticated as profile '{$profile}' at {$baseUrl} (unverified)");

                return Command::SUCCESS;
            }

            if ($result['status'] === 401 || $result['status'] === 403) {
                $auth->logout($profile);
                $formatter->error('INVALID_TOKEN', "The API at {$baseUrl} rejected this token (HTTP {$result['status']}). Create a personal access token in the Zelta dashboard (Profile -> API Tokens) and try again.");

                return 2;
            }

            /** @var array<string, mixed> $userData */
            $userData = $result['body']['data'] ?? [];
            $email = is_array($userData) ? ($userData['email'] ?? null) : null;
            if (is_string($email) && $email !== '') {
                $formatter->success("Authenticated as {$email} (profile '{$profile}') at {$baseUrl}");

                return Command::SUCCESS;
            }
        }

        $formatter->success("Authenticated as profile '{$profile}' at {$baseUrl}");

        return Command::SUCCESS;
    }
}
