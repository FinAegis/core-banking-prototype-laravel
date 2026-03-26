<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Agents;

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
 * zelta agents register --did did:web:... --name <name> --capabilities payment,messaging.
 */
class RegisterCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('agents:register');
        $this->setDescription('Register a new AI agent');
    }

    protected function configure(): void
    {
        $this
            ->addOption('did', null, InputOption::VALUE_REQUIRED, 'Agent DID (e.g., did:web:example.com)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Agent display name')
            ->addOption('capabilities', null, InputOption::VALUE_OPTIONAL, 'Comma-separated capabilities (e.g., payment,messaging)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        if (! $this->ensureAuthenticated($auth, $output)) {
            return 2;
        }

        /** @var string|null $did */
        $did = $input->getOption('did');
        /** @var string|null $name */
        $name = $input->getOption('name');

        if ($did === null || $name === null) {
            $formatter->error('VALIDATION', 'Both --did and --name are required');

            return 4;
        }

        /** @var string|null $capabilities */
        $capabilities = $input->getOption('capabilities');

        $payload = [
            'did'  => $did,
            'name' => $name,
        ];

        if ($capabilities !== null) {
            $payload['capabilities'] = array_map('trim', explode(',', $capabilities));
        }

        $api = new ApiClient($auth);
        $result = $api->post('/v1/agent-protocol/agents/register', $payload);

        if ($result['status'] >= 400) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? "HTTP {$result['status']}");

            return Command::FAILURE;
        }

        $formatter->output($result['body']['data'] ?? $result['body'], forceJson: $this->shouldOutputJson($input));

        return Command::SUCCESS;
    }
}
