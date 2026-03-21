<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Services;

use App\Domain\VisaCli\Contracts\VisaCliClientInterface;
use App\Domain\VisaCli\DataObjects\VisaCliCard;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentResult;
use App\Domain\VisaCli\DataObjects\VisaCliStatus;
use App\Domain\VisaCli\Enums\VisaCliCardStatus;
use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;
use App\Domain\VisaCli\Exceptions\VisaCliException;
use App\Domain\VisaCli\Exceptions\VisaCliPaymentException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Calls the visa-cli binary via Symfony Process.
 */
class VisaCliProcessClient implements VisaCliClientInterface
{
    private readonly string $binaryPath;

    private readonly int $defaultTimeout;

    public function __construct()
    {
        $this->binaryPath = (string) config('visacli.binary_path', 'visa-cli');
        $this->defaultTimeout = (int) config('visacli.timeouts.status', 10);
    }

    public function getStatus(): VisaCliStatus
    {
        $output = $this->runCommand(['status', '--json']);

        /** @var array<string, mixed> $data */
        $data = json_decode($output, true) ?? [];

        return new VisaCliStatus(
            initialized: (bool) ($data['initialized'] ?? false),
            version: $data['version'] ?? null,
            githubUsername: $data['github_username'] ?? null,
            enrolledCards: (int) ($data['enrolled_cards'] ?? 0),
            metadata: $data,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function enrollCard(string $userId, array $metadata = []): VisaCliCard
    {
        $output = $this->runCommand(
            ['enroll-card', '--json'],
            (int) config('visacli.timeouts.enrollment', 60),
        );

        /** @var array<string, mixed> $data */
        $data = json_decode($output, true) ?? [];

        return new VisaCliCard(
            cardIdentifier: (string) ($data['card_id'] ?? ''),
            last4: (string) ($data['last4'] ?? ''),
            network: (string) ($data['network'] ?? 'visa'),
            status: VisaCliCardStatus::ENROLLED,
            githubUsername: $data['github_username'] ?? null,
            metadata: array_merge($metadata, ['user_id' => $userId]),
        );
    }

    /**
     * @return array<VisaCliCard>
     */
    public function listCards(?string $userId = null): array
    {
        $output = $this->runCommand(['cards', '--json']);

        /** @var array<int, array<string, mixed>> $data */
        $data = json_decode($output, true) ?? [];

        return array_map(
            fn (array $card) => new VisaCliCard(
                cardIdentifier: (string) ($card['card_id'] ?? ''),
                last4: (string) ($card['last4'] ?? ''),
                network: (string) ($card['network'] ?? 'visa'),
                status: VisaCliCardStatus::tryFrom((string) ($card['status'] ?? 'enrolled')) ?? VisaCliCardStatus::ENROLLED,
                githubUsername: $card['github_username'] ?? null,
                metadata: (array) ($card['metadata'] ?? []),
            ),
            $data,
        );
    }

    public function pay(string $url, int $amountCents, ?string $cardId = null): VisaCliPaymentResult
    {
        // Validate inputs before passing to shell
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new VisaCliPaymentException("Invalid payment URL: {$url}");
        }

        if ($cardId !== null && ! preg_match('/^[a-zA-Z0-9_\-]{1,255}$/', $cardId)) {
            throw new VisaCliPaymentException('Invalid card identifier format.');
        }

        $args = ['pay', $url, '--amount', (string) $amountCents, '--json'];
        if ($cardId !== null) {
            $args[] = '--card';
            $args[] = $cardId;
        }

        try {
            $output = $this->runCommand(
                $args,
                (int) config('visacli.timeouts.payment', 30),
            );

            /** @var array<string, mixed> $data */
            $data = json_decode($output, true) ?? [];

            return new VisaCliPaymentResult(
                paymentReference: (string) ($data['payment_reference'] ?? ''),
                status: VisaCliPaymentStatus::tryFrom((string) ($data['status'] ?? 'completed')) ?? VisaCliPaymentStatus::COMPLETED,
                amountCents: (int) ($data['amount_cents'] ?? $amountCents),
                currency: (string) ($data['currency'] ?? 'USD'),
                url: $url,
                cardLast4: $data['card_last4'] ?? null,
                metadata: $data,
            );
        } catch (VisaCliException $e) {
            throw new VisaCliPaymentException(
                "Payment failed for URL {$url}: " . $e->getMessage(),
                $e->getCode(),
                $e,
            );
        }
    }

    public function isInitialized(): bool
    {
        try {
            $status = $this->getStatus();

            return $status->initialized;
        } catch (VisaCliException) {
            return false;
        }
    }

    public function initialize(): bool
    {
        try {
            $this->runCommand(['init']);

            return true;
        } catch (VisaCliException $e) {
            Log::error('Visa CLI initialization failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @param array<string> $args
     */
    private function runCommand(array $args, ?int $timeout = null): string
    {
        $command = array_merge([$this->binaryPath], $args);

        $env = [];
        $githubToken = config('visacli.auth.github_token');
        if ($githubToken !== null) {
            $env['GITHUB_TOKEN'] = (string) $githubToken;
        }

        $process = new Process($command, null, $env !== [] ? $env : null);
        $process->setTimeout($timeout ?? $this->defaultTimeout);

        // Log command without sensitive environment variables
        Log::debug('Visa CLI: executing command', ['command' => $args[0] ?? 'unknown', 'args_count' => count($args)]);

        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput() ?: $process->getOutput();
            // Redact potential secrets from error output
            $safeOutput = preg_replace('/ghp_[a-zA-Z0-9]+/', 'ghp_***REDACTED***', $errorOutput) ?? $errorOutput;

            Log::error('Visa CLI command failed', [
                'command'   => $args[0] ?? 'unknown',
                'exit_code' => $process->getExitCode(),
                'error'     => $safeOutput,
            ]);

            throw new VisaCliException(
                "Visa CLI command failed: {$errorOutput}",
                $process->getExitCode() ?? 1,
            );
        }

        return $process->getOutput();
    }
}
