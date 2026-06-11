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
 * zelta ramp:kyc-link.
 *
 * Issues a hosted KYC link (Bridge.xyz) the user opens in a browser to
 * complete identity verification for bank-rail deposits. The server lazily
 * provisions the Bridge customer on first call (idempotent server-side).
 */
class KycLinkCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('ramp:kyc-link');
        $this->setDescription('Get a hosted KYC link to set up bank transfers');
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
        $result = $api->post('/v1/user/bridge-kyc-link');

        // 409 KYC_ALREADY_APPROVED / 501 PROVIDER_NOT_IMPLEMENTED carry a
        // machine-readable `error` code — surface it verbatim.
        if ($result['status'] !== 200) {
            $formatter->error(
                $result['body']['error'] ?? 'API_ERROR',
                $result['body']['message'] ?? "HTTP {$result['status']}",
            );

            return Command::FAILURE;
        }

        /** @var array{url?: string, expiresAt?: string|null} $link */
        $link = $result['body'];

        if ($this->shouldOutputJson($input)) {
            $formatter->json($link);

            return Command::SUCCESS;
        }

        $url = $link['url'] ?? '';
        if ($url === '') {
            $formatter->error('API_ERROR', 'No KYC link URL in the response.');

            return Command::FAILURE;
        }

        $formatter->success('Open this link in a browser to complete identity verification:');
        $output->writeln($url);

        $expiresAt = $link['expiresAt'] ?? null;
        if (is_string($expiresAt) && $expiresAt !== '') {
            $output->writeln("<comment>Link expires at: {$expiresAt}</comment>");
        }

        return Command::SUCCESS;
    }
}
