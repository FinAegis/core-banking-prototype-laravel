<?php

namespace App\Domain\Cgo\Jobs;

use App\Domain\Cgo\Models\CgoInvestment;
use App\Domain\Cgo\Services\PaymentVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyCgoPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected CgoInvestment $investment;

    protected int $attempt;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(CgoInvestment $investment, int $attempt = 1)
    {
        $this->investment = $investment;
        $this->attempt = $attempt;
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentVerificationService $verificationService): void
    {
        // Skip if already confirmed
        if ($this->investment->status === 'confirmed') {
            Log::info(
                'Investment already confirmed, skipping verification',
                [
                'investment_id' => $this->investment->id,
                ]
            );

            return;
        }

        // Check if payment is expired
        if ($verificationService->isPaymentExpired($this->investment)) {
            Log::warning(
                'Payment expired, marking as cancelled',
                [
                'investment_id' => $this->investment->id,
                ]
            );

            $this->investment->update(
                [
                'status'                 => 'cancelled',
                'cancelled_at'           => now(),
                'payment_status'         => 'expired',
                'payment_failure_reason' => 'Payment window expired',
                ]
            );

            return;
        }

        // Attempt to verify payment
        $verified = $verificationService->verifyPayment($this->investment);

        if (! $verified && $this->attempt < 3) {
            // Retry with exponential backoff
            $delay = $this->attempt * 300; // 5 minutes, 10 minutes, etc.

            Log::info(
                'Payment not verified, scheduling retry',
                [
                'investment_id' => $this->investment->id,
                'attempt'       => $this->attempt,
                'delay'         => $delay,
                ]
            );

            self::dispatch($this->investment, $this->attempt + 1)->delay(now()->addSeconds($delay));
        } elseif (! $verified) {
            Log::warning(
                'Payment verification failed after multiple attempts',
                [
                'investment_id' => $this->investment->id,
                'attempts'      => $this->attempt,
                ]
            );
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1 minute, 5 minutes, 15 minutes
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error(
            'CGO payment verification job failed',
            [
            'investment_id' => $this->investment->id,
            'error'         => $exception->getMessage(),
            'trace'         => $exception->getTraceAsString(),
            ]
        );

        // Mark payment as requiring manual verification
        $this->investment->update(
            [
            'payment_status' => 'verification_failed',
            'notes'          => 'Automatic payment verification failed. Manual review required.',
            ]
        );
    }
}
