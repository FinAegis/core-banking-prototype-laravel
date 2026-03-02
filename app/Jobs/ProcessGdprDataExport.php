<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Compliance\Services\GdprService;
use App\Models\User;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Process a GDPR data export request asynchronously.
 *
 * Collects all user data sections and stores the result in cache
 * for retrieval via the export status endpoint.
 */
class ProcessGdprDataExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly int $userId,
        private readonly string $exportId,
    ) {
        $this->onQueue('default');
    }

    public function handle(GdprService $gdprService): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            Log::warning('GDPR export: user not found', ['user_id' => $this->userId, 'export_id' => $this->exportId]);
            Cache::put($this->cacheKey(), [
                'status' => 'failed',
                'error'  => 'User not found',
            ], now()->addHours(24));

            return;
        }

        try {
            Cache::put($this->cacheKey(), [
                'status'     => 'processing',
                'started_at' => now()->toIso8601String(),
            ], now()->addHours(24));

            $data = $gdprService->exportUserData($user);

            // Write encrypted export to disk for download
            $filePath = "gdpr-exports/{$this->exportId}.json.enc";
            $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            Storage::disk('local')->put($filePath, encrypt($jsonData));

            $downloadUrl = URL::signedRoute(
                'api.user.data-export.download',
                ['exportId' => $this->exportId],
                now()->addHour(),
            );

            Cache::put($this->cacheKey(), [
                'status'       => 'completed',
                'sections'     => array_keys($data),
                'completed_at' => now()->toIso8601String(),
                'file_path'    => $filePath,
                'download_url' => $downloadUrl,
            ], now()->addHours(24));

            Log::info('GDPR data export completed', [
                'user_id'   => $this->userId,
                'export_id' => $this->exportId,
                'sections'  => array_keys($data),
            ]);
        } catch (Exception $e) {
            Log::error('GDPR data export failed', [
                'user_id'   => $this->userId,
                'export_id' => $this->exportId,
                'error'     => $e->getMessage(),
            ]);

            Cache::put($this->cacheKey(), [
                'status' => 'failed',
                'error'  => 'Export processing failed',
            ], now()->addHours(24));

            throw $e;
        }
    }

    private function cacheKey(): string
    {
        return "gdpr_export:{$this->exportId}";
    }
}
