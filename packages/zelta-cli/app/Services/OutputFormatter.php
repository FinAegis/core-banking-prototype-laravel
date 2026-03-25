<?php

declare(strict_types=1);

namespace ZeltaCli\Services;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dual-mode output: tables/colors for humans, JSON for pipes.
 *
 * Detects TTY to choose output format — interactive for terminals,
 * machine-readable JSON when piped to other programs or used by AI agents.
 */
class OutputFormatter
{
    public function __construct(
        private readonly OutputInterface $output,
    ) {
    }

    /**
     * Check if output is going to a terminal (not piped).
     */
    public function isInteractive(): bool
    {
        if (defined('STDIN') && function_exists('posix_isatty')) {
            return posix_isatty(STDOUT);
        }

        return true;
    }

    /**
     * Output data as JSON (always) or table (if interactive).
     *
     * @param array<string, mixed>|list<array<string, mixed>> $data
     * @param list<string> $headers Column headers for table mode
     */
    public function output(array $data, array $headers = [], bool $forceJson = false): void
    {
        if ($forceJson || ! $this->isInteractive()) {
            $this->json($data);

            return;
        }

        if ($headers !== [] && array_is_list($data)) {
            $this->table($headers, $data);

            return;
        }

        $this->json($data);
    }

    /**
     * Output raw JSON.
     *
     * @param array<string, mixed>|list<array<string, mixed>> $data
     */
    public function json(array $data): void
    {
        $this->output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    /**
     * Output a formatted table.
     *
     * @param list<string> $headers
     * @param list<array<string, mixed>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        // Simple table output using Symfony Console
        $widths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $value) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $value));
            }
        }

        // Header
        $headerLine = '';
        foreach ($headers as $i => $header) {
            $headerLine .= str_pad($header, $widths[$i] + 2);
        }
        $this->output->writeln("<info>{$headerLine}</info>");
        $this->output->writeln(str_repeat('-', array_sum($widths) + count($widths) * 2));

        // Rows
        foreach ($rows as $row) {
            $line = '';
            foreach (array_values($row) as $i => $value) {
                $line .= str_pad((string) $value, ($widths[$i] ?? 0) + 2);
            }
            $this->output->writeln($line);
        }
    }

    /**
     * Output a success message.
     */
    public function success(string $message): void
    {
        if ($this->isInteractive()) {
            $this->output->writeln("<info>{$message}</info>");
        } else {
            $this->json(['status' => 'success', 'message' => $message]);
        }
    }

    /**
     * Output an error message.
     */
    public function error(string $code, string $message): void
    {
        if ($this->isInteractive()) {
            $this->output->writeln("<error>{$code}: {$message}</error>");
        } else {
            $this->json(['error' => $code, 'message' => $message]);
        }
    }
}
