<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\VisaCli\DataObjects\VisaCliPaymentRequest;
use App\Domain\VisaCli\Services\VisaCliPaymentService;
use Illuminate\Console\Command;
use Throwable;

class VisaCliPayCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'visa:pay
                            {url : The payment target URL}
                            {--amount= : Payment amount in USD cents}
                            {--card= : Card identifier to use}
                            {--agent=cli-user : Agent identifier}';

    /**
     * @var string
     */
    protected $description = 'Execute a Visa CLI payment to a URL';

    public function handle(VisaCliPaymentService $paymentService): int
    {
        if (! config('visacli.enabled', false)) {
            $this->warn('Visa CLI integration is disabled. Set VISACLI_ENABLED=true to enable.');

            return 1;
        }

        $url = (string) $this->argument('url');
        $amountCents = (int) $this->option('amount');
        $cardId = $this->option('card');
        $agentId = (string) $this->option('agent');

        if ($amountCents <= 0) {
            $amountInput = $this->ask('Enter payment amount in USD cents');
            $amountCents = (int) $amountInput;
        }

        if ($amountCents <= 0) {
            $this->error('Amount must be a positive integer (cents).');

            return 1;
        }

        $this->info('Processing payment...');
        $this->line("  URL:    {$url}");
        $this->line('  Amount: $' . number_format($amountCents / 100, 2));
        $this->line("  Agent:  {$agentId}");

        if (! $this->confirm('Proceed with payment?', true)) {
            $this->info('Payment cancelled.');

            return 0;
        }

        try {
            $request = new VisaCliPaymentRequest(
                agentId: $agentId,
                url: $url,
                amountCents: $amountCents,
                cardId: is_string($cardId) ? $cardId : null,
                purpose: 'cli_payment',
            );

            $result = $this->paymentService($paymentService, $request);

            $this->newLine();
            $this->info('Payment completed!');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Reference', $result->paymentReference],
                    ['Status', $result->status->value],
                    ['Amount', '$' . number_format($result->amountCents / 100, 2)],
                    ['Currency', $result->currency],
                    ['Card Last 4', $result->cardLast4 ?? 'N/A'],
                ]
            );

            return 0;
        } catch (Throwable $e) {
            $this->error("Payment failed: {$e->getMessage()}");

            return 1;
        }
    }

    private function paymentService(
        VisaCliPaymentService $paymentService,
        VisaCliPaymentRequest $request,
    ): \App\Domain\VisaCli\DataObjects\VisaCliPaymentResult {
        return $paymentService->executePayment($request);
    }
}
