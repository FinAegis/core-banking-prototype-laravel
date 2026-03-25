<?php

declare(strict_types=1);

namespace ZeltaCli\Concerns;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Adds --json flag support to commands.
 */
trait HasJsonOutput
{
    protected function configureJsonOption(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function shouldOutputJson(?InputInterface $input = null): bool
    {
        if ($input !== null && $input->getOption('json')) {
            return true;
        }

        return defined('STDOUT') && function_exists('posix_isatty') && ! posix_isatty(STDOUT);
    }
}
