<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Ramp;

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
 * zelta ramp:status.
 *
 * Bank-rail (Bridge.xyz) setup state for the authenticated user: KYC
 * status, virtual-account readiness, and supported rails. Read-only —
 * the server reads its local webhook-populated cache, never Bridge.
 */
class StatusCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('ramp:status');
        $this->setDescription('Show bank-transfer (ramp) setup status: KYC + virtual account');
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
        $result = $api->get('/v1/user/bridge-setup-status');

        if ($result['status'] !== 200) {
            $formatter->error('API_ERROR', $result['body']['message'] ?? "HTTP {$result['status']}");

            return Command::FAILURE;
        }

        /** @var array{kycStatus?: string, virtualAccountReady?: bool, supportedRails?: list<string>} $status */
        $status = $result['body'];

        if ($this->shouldOutputJson($input)) {
            $formatter->json($status);

            return Command::SUCCESS;
        }

        $kycStatus = $status['kycStatus'] ?? 'unknown';
        $vaReady = ($status['virtualAccountReady'] ?? false) === true;
        $rails = $status['supportedRails'] ?? [];

        $formatter->table(['Field', 'Value'], [
            ['KYC status', $kycStatus],
            ['Virtual account', $vaReady ? 'ready' : 'not ready'],
            ['Supported rails', $rails === [] ? '-' : implode(', ', $rails)],
        ]);

        $output->writeln('');
        $output->writeln('<comment>' . $this->nextStepHint($kycStatus, $vaReady) . '</comment>');

        return Command::SUCCESS;
    }

    private function nextStepHint(string $kycStatus, bool $vaReady): string
    {
        return match (true) {
            $kycStatus === 'approved' && $vaReady => 'Bank transfers are ready.',
            $kycStatus === 'approved'             => 'KYC approved — virtual account provisioning is in progress; check again shortly.',
            $kycStatus === 'pending'              => 'KYC is in review — check again shortly.',
            $kycStatus === 'rejected'             => 'KYC was rejected — contact support.',
            default                               => 'Run "zelta ramp:kyc-link" to start identity verification.',
        };
    }
}
