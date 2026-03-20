<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\VisaCli\Contracts\VisaCliClientInterface;
use App\Domain\VisaCli\Models\VisaCliPayment;
use Illuminate\Console\Command;

class VisaCliStatusCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'visa:status';

    /**
     * @var string
     */
    protected $description = 'Show Visa CLI status: initialization state, enrolled cards, and recent payments';

    public function handle(VisaCliClientInterface $client): int
    {
        if (! config('visacli.enabled', false)) {
            $this->warn('Visa CLI integration is disabled. Set VISACLI_ENABLED=true to enable.');

            return 0;
        }

        $this->info('Visa CLI Status');
        $this->line('');

        // Status
        $status = $client->getStatus();
        $this->table(
            ['Property', 'Value'],
            [
                ['Initialized', $status->initialized ? 'Yes' : 'No'],
                ['Version', $status->version ?? 'N/A'],
                ['GitHub User', $status->githubUsername ?? 'N/A'],
                ['Driver', (string) config('visacli.driver', 'demo')],
                ['Enrolled Cards', (string) $status->enrolledCards],
            ]
        );

        // Cards
        $cards = $client->listCards();
        if ($cards !== []) {
            $this->line('');
            $this->info('Enrolled Cards');
            $this->table(
                ['Identifier', 'Last 4', 'Network', 'Status'],
                array_map(fn ($card) => [
                    $card->cardIdentifier,
                    $card->last4,
                    $card->network,
                    $card->status->value,
                ], $cards)
            );
        }

        // Recent payments
        $payments = VisaCliPayment::orderBy('created_at', 'desc')->limit(10)->get();
        if ($payments->isNotEmpty()) {
            $this->line('');
            $this->info('Recent Payments (last 10)');
            $this->table(
                ['ID', 'Agent', 'URL', 'Amount', 'Status', 'Created'],
                $payments->map(fn (VisaCliPayment $p) => [
                    substr($p->id, 0, 8) . '...',
                    $p->agent_id,
                    strlen($p->url) > 40 ? substr($p->url, 0, 40) . '...' : $p->url,
                    '$' . number_format($p->amount_cents / 100, 2),
                    is_string($p->status) ? $p->status : $p->status->value,
                    $p->created_at->format('Y-m-d H:i'),
                ])->toArray()
            );
        } else {
            $this->line('');
            $this->info('No payments recorded yet.');
        }

        return 0;
    }
}
