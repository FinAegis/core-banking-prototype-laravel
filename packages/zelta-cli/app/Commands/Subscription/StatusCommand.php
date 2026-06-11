<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Subscription;

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
 * zelta subscription:status.
 *
 * Shows the authenticated user's subscription (free/pro tier, source,
 * period end). Read-only — plan changes happen in the app / web checkout.
 */
class StatusCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('subscription:status');
        $this->setDescription('Show your subscription tier, status, and period end');
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        if (! $this->ensureAuthenticated($auth, $output)) {
            return 2;
        }

        $api = new ApiClient($auth);
        $result = $api->get('/v1/subscription/me');

        if ($result['status'] !== 200) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? "HTTP {$result['status']}");

            return Command::FAILURE;
        }

        // Flat shape (no `data` wrapper): tier, status, source, plan,
        // currentPeriodEnd, trialEndsAt, cancelledAtPeriodEnd, pausedUntil.
        /** @var array<string, mixed> $subscription */
        $subscription = $result['body'];

        if ($this->shouldOutputJson($input)) {
            $formatter->json($subscription);

            return Command::SUCCESS;
        }

        $rows = [];
        foreach (['tier', 'status', 'source', 'plan', 'currentPeriodEnd', 'trialEndsAt', 'cancelledAtPeriodEnd', 'pausedUntil'] as $field) {
            $value = $subscription[$field] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $rows[] = [$field, is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value];
        }

        if ($rows === []) {
            $formatter->success('No subscription found — you are on the free tier.');

            return Command::SUCCESS;
        }

        $formatter->table(['Field', 'Value'], $rows);

        return Command::SUCCESS;
    }
}
