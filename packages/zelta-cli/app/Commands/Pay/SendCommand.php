<?php

declare(strict_types=1);

namespace ZeltaCli\Commands\Pay;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zelta\DataObjects\PaymentConfig;
use Zelta\Exceptions\PaymentFailedException;
use Zelta\Exceptions\PaymentRequiredException;
use Zelta\Handlers\AutoDetectHandler;
use Zelta\Handlers\MppPaymentHandler;
use Zelta\ZeltaClient;
use ZeltaCli\Concerns\HasJsonOutput;
use ZeltaCli\Concerns\RequiresAuth;
use ZeltaCli\Services\AuthManager;
use ZeltaCli\Services\OutputFormatter;

/**
 * zelta pay send <url> [--yes].
 *
 * Fetches a paid endpoint. On a 402 response the payment SDK
 * (finaegis/payment-sdk) negotiates the challenge and retries once with
 * the payment credential attached — there is no server-side
 * "POST a payment" endpoint; the protocols are header-based.
 *
 * MPP challenges are auto-handled. x402 challenges require an on-device
 * signer (EIP-712 / Ed25519 key material) the CLI does not hold, so they
 * are surfaced with their requirements instead of being auto-paid.
 */
class SendCommand extends Command
{
    use HasJsonOutput;
    use RequiresAuth;

    public function __construct()
    {
        parent::__construct('pay:send');
        $this->setDescription('Pay for an API endpoint via MPP/x402');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED, 'Target URL that requires payment')
            ->addOption('amount', null, InputOption::VALUE_OPTIONAL, 'Display-only amount hint (actual amount comes from the 402 challenge)')
            // No "-n" shortcut: it collides with Symfony's built-in --no-interaction.
            ->addOption('network', null, InputOption::VALUE_OPTIONAL, 'Display-only network hint (rail is negotiated from the challenge)', 'eip155:8453')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt and auto-pay')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatter = new OutputFormatter($output);
        $auth = new AuthManager();

        if (! $this->ensureAuthenticated($auth, $output)) {
            return 2;
        }

        /** @var string $url */
        $url = $input->getArgument('url');
        $autoPay = (bool) $input->getOption('yes');

        $client = new ZeltaClient(
            config: new PaymentConfig(
                baseUrl: $auth->getBaseUrl(),
                apiKey: $auth->getApiKey(),
                autoPay: $autoPay,
            ),
            payment: new AutoDetectHandler(new MppPaymentHandler()),
        );

        try {
            $result = $client->get($url);
        } catch (PaymentRequiredException $e) {
            $amount = $input->getOption('amount') ?? ($e->requirements['amount'] ?? 'unknown');

            if (! $autoPay) {
                $output->writeln("Payment required: {$amount}");
                $output->writeln('Use --yes to confirm payment.');

                return Command::SUCCESS;
            }

            // --yes was given but the challenge could not be satisfied —
            // e.g. an x402-only endpoint, which needs a local signing key.
            $formatter->error(
                'PAYMENT_UNSUPPORTED',
                'This 402 challenge cannot be auto-paid by the CLI (x402 requires an on-device signer). Requirements: '
                . (json_encode($e->requirements) ?: '{}'),
            );

            return 3;
        } catch (PaymentFailedException $e) {
            $formatter->error('PAYMENT_FAILED', $e->getMessage());

            return 3;
        }

        if ($result->statusCode >= 400) {
            $formatter->error('API_ERROR', (string) ($result->body['message'] ?? "HTTP {$result->statusCode}"));

            return Command::FAILURE;
        }

        /** @var array<string, mixed> $body */
        $body = $result->body['data'] ?? $result->body;
        $formatter->output($body, forceJson: $this->shouldOutputJson($input));

        if ($result->paid && ! $this->shouldOutputJson($input)) {
            $output->writeln('<info>Payment settled via MPP — request retried successfully.</info>');
        }

        return Command::SUCCESS;
    }
}
