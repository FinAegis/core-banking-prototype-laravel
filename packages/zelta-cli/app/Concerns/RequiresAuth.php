<?php

declare(strict_types=1);

namespace ZeltaCli\Concerns;

use ZeltaCli\Services\AuthManager;

/**
 * Checks credentials before command execution.
 */
trait RequiresAuth
{
    protected function ensureAuthenticated(AuthManager $auth): bool
    {
        if ($auth->isAuthenticated()) {
            return true;
        }

        $this->error('Not authenticated. Run: zelta auth login --key <your-api-key>');

        return false;
    }
}
