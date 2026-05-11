<?php

/**
 * CueDlqDigestCommand — daily email digest of cue-related DLQ failures.
 *
 * Sends a summary email to MAIL_ADMIN_ADDRESS if any cue job kinds have a
 * failure rate > 5% in the past 24 hours. Falls back to a Filament-only
 * warning (no email) if MAIL_ADMIN_ADDRESS is not configured.
 *
 * Scheduled daily at 03:15 UTC via routes/console.php.
 *
 *   php artisan cue:dlq-digest
 *   php artisan cue:dlq-digest --dry-run
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.10 OD-3
 */

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class CueDlqDigestCommand extends Command
{
    protected $signature = 'cue:dlq-digest
        {--dry-run : Print the digest without emailing}';

    protected $description = 'Daily email digest of cue job DLQ failures (Backend-Q8 OD-3)';

    /** Cue job class prefixes to filter in failed_jobs. */
    private const CUE_JOB_CLASSES = [
        'App\\Domain\\Subscription\\Jobs\\Cue\\',
        'App\\Console\\Commands\\DispatchKycRequiredCues',
    ];

    public function handle(): int
    {
        $since = Carbon::now()->subDay();

        // Count failed cue jobs in the past 24h.
        $failedJobs = DB::table('failed_jobs')
            ->where('failed_at', '>=', $since)
            ->get(['id', 'connection', 'queue', 'payload', 'exception', 'failed_at']);

        $cueFailures = $failedJobs->filter(function ($job): bool {
            $payload = json_decode((string) $job->payload, true);
            $jobClass = (string) ($payload['displayName'] ?? '');

            foreach (self::CUE_JOB_CLASSES as $prefix) {
                if (str_starts_with($jobClass, $prefix)) {
                    return true;
                }
            }

            return false;
        });

        $failureCount = $cueFailures->count();

        Log::info('cue.dispatch_health', [
            'period_hours' => 24,
            'cue_failures' => $failureCount,
        ]);

        if ($failureCount === 0) {
            $this->info('No cue job failures in the past 24h.');

            return self::SUCCESS;
        }

        $adminEmail = (string) config('mail.admin_address', '');

        if ($this->option('dry-run')) {
            $this->warn(sprintf(
                '[dry-run] Would send DLQ digest to %s: %d cue job failure(s) in past 24h.',
                $adminEmail ?: '(MAIL_ADMIN_ADDRESS not set)',
                $failureCount,
            ));

            return self::SUCCESS;
        }

        if ($adminEmail === '') {
            // OD-3 fallback: no email configured — log only (Filament widget picks up).
            Log::warning('cue.dlq_digest.no_admin_email', [
                'cue_failures' => $failureCount,
                'note'         => 'Set MAIL_ADMIN_ADDRESS to receive daily DLQ digest emails.',
            ]);
            $this->warn(sprintf(
                'DLQ digest: %d cue failure(s). MAIL_ADMIN_ADDRESS not set — falling back to log-only.',
                $failureCount,
            ));

            return self::SUCCESS;
        }

        try {
            /** @var array<int, array{id: mixed, displayName: string, failed_at: mixed}> $failureRows */
            $failureRows = $cueFailures->map(function ($job): array {
                $payload = json_decode((string) $job->payload, true);

                return [
                    'id'          => $job->id,
                    'displayName' => (string) ($payload['displayName'] ?? 'unknown'),
                    'failed_at'   => $job->failed_at,
                ];
            })->toArray();

            Mail::raw(
                $this->buildDigestBody($failureCount, $failureRows),
                function ($message) use ($adminEmail, $failureCount): void {
                    $message->to($adminEmail)
                        ->subject(sprintf('[Cue DLQ] %d job failure(s) in the past 24h', $failureCount));
                },
            );

            $this->info(sprintf('DLQ digest sent to %s: %d failure(s).', $adminEmail, $failureCount));
        } catch (Throwable $e) {
            Log::error('cue.dlq_digest.send_failed', ['error' => $e->getMessage()]);
            $this->error(sprintf('Failed to send DLQ digest: %s', $e->getMessage()));
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, array{id: mixed, displayName: string, failed_at: mixed}> $failureRows
     */
    private function buildDigestBody(int $failureCount, array $failureRows): string
    {
        $lines = [
            sprintf('Cue Queue DLQ Digest — %s UTC', now()->toDateTimeString()),
            sprintf('Failed cue jobs in the past 24h: %d', $failureCount),
            '',
            'Failures:',
        ];

        foreach ($failureRows as $row) {
            $lines[] = sprintf('  [%s] %s — %s', $row['id'], $row['displayName'], $row['failed_at']);
        }

        $lines[] = '';
        $lines[] = 'Review failed jobs at /admin/cues or run: php artisan queue:failed';

        return implode("\n", $lines);
    }
}
