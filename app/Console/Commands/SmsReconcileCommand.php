<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\SMS\Clients\VertexSmsClient;
use App\Domain\SMS\Models\SmsMessage;
use App\Domain\SMS\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reconciles SMS messages stuck in "sent" past the VertexSMS expiration window.
 *
 * VertexSMS guarantees a final DLR (delivered or undelivered with error=2) before
 * the expiration time (default 3 days / 259200s). If no DLR was received by then,
 * this command polls GET /sms/status/{id} as a fallback.
 *
 * Safe to run on a schedule (e.g. daily). No rate limit on the status endpoint (Q11).
 */
class SmsReconcileCommand extends Command
{
    protected $signature = 'sms:reconcile
        {--limit=100 : Maximum messages to reconcile per run}
        {--dry-run : Show what would be reconciled without making changes}';

    protected $description = 'Reconcile SMS messages with missing DLR reports by polling VertexSMS status API';

    public function handle(VertexSmsClient $client, SmsService $smsService): int
    {
        $expireSeconds = (int) config('sms.defaults.expire_seconds', 259200);
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        if ($limit <= 0) {
            $this->error('--limit must be a positive integer.');

            return self::FAILURE;
        }

        $cutoff = now()->subSeconds($expireSeconds);

        $stale = SmsMessage::where('status', SmsMessage::STATUS_SENT)
            ->where('provider', 'vertexsms')
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No stale messages to reconcile.');

            return self::SUCCESS;
        }

        $this->info("Found {$stale->count()} stale message(s) past {$expireSeconds}s expiration window.");

        $reconciled = 0;
        $errors = 0;

        foreach ($stale as $sms) {
            $providerId = (string) $sms->provider_id;

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would reconcile: {$providerId} (created {$sms->created_at})");

                continue;
            }

            try {
                $remote = $client->getMessageStatus($providerId);

                if ($remote === null) {
                    $this->warn("  Could not fetch status for {$providerId} — skipping");
                    $errors++;

                    continue;
                }

                $smsService->handleDeliveryReport([
                    'message_id' => $providerId,
                    'raw_status' => $remote['status'],
                    'error_code' => $remote['error'],
                ]);

                $reconciled++;
                $this->line("  Reconciled {$providerId}: status={$remote['status']}, error={$remote['error']}");
            } catch (Throwable $e) {
                $errors++;
                $this->error("  Failed to reconcile {$providerId}: {$e->getMessage()}");
                Log::error('sms:reconcile failed', [
                    'provider_id' => $providerId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Reconciled: {$reconciled}, Errors: {$errors}");

        return $errors > 0 && $reconciled === 0 ? self::FAILURE : self::SUCCESS;
    }
}
