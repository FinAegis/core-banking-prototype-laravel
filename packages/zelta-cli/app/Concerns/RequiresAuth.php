<?php

declare(strict_types=1);

namespace ZeltaCli\Concerns;

use Symfony\Component\Console\Output\OutputInterface;
use ZeltaCli\Services\AuthManager;

/**
 * Checks credentials before command execution.
 */
trait RequiresAuth
{
    protected function ensureAuthenticated(AuthManager $auth, ?OutputInterface $output = null): bool
    {
        if ($auth->isAuthenticated()) {
            return true;
        }

        if ($output !== null) {
            $output->writeln('<error>Not authenticated. Run: zelta auth login --key <your-api-key></error>');
        }

        return false;
    }
}
