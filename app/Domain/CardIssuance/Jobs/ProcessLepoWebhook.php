<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Jobs;

use App\Domain\CardIssuance\Webhooks\LepoWebhookRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Processes one verified, deduplicated Lepo webhook event. The payload was
 * redacted before dispatch (no 3-D Secure codes), so a failed job keeps
 * nothing sensitive in failed_jobs.
 */
final class ProcessLepoWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $type,
        public readonly array $data,
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(LepoWebhookRouter $router): void
    {
        $router->route($this->type, $this->data, $this->eventId);
    }
}
