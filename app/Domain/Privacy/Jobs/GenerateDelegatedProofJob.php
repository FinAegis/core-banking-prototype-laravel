<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Jobs;

use App\Domain\Privacy\Events\DelegatedProofCompleted;
use App\Domain\Privacy\Events\DelegatedProofFailed;
use App\Domain\Privacy\Events\DelegatedProofProgress;
use App\Domain\Privacy\Models\DelegatedProofJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background job for generating ZK proofs.
 *
 * In demo mode, simulates proof generation with progress updates.
 * In production, would integrate with actual ZK proving infrastructure
 * (e.g., snarkjs, circom, or dedicated proving service).
 */
class GenerateDelegatedProofJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout;

    public function __construct(
        public readonly DelegatedProofJob $proofJob,
    ) {
        $this->timeout = (int) config('privacy.delegated_proving.timeout_seconds', 300);
        $this->onQueue('proofs');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting delegated proof generation', [
            'job_id'     => $this->proofJob->id,
            'proof_type' => $this->proofJob->proof_type,
            'network'    => $this->proofJob->network,
        ]);

        try {
            // Mark as proving
            $this->proofJob->update(['status' => DelegatedProofJob::STATUS_PROVING]);

            // Simulate proof generation with progress updates
            $this->generateProofWithProgress();

            // Generate the proof (demo mode returns deterministic proof)
            $proof = $this->generateDemoProof();

            // Mark as completed
            $this->proofJob->markCompleted($proof);

            Log::info('Delegated proof completed', [
                'job_id' => $this->proofJob->id,
            ]);

            // Broadcast completion
            event(new DelegatedProofCompleted($this->proofJob));

        } catch (Throwable $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        $this->handleFailure($exception);
    }

    /**
     * Simulate proof generation with progress updates.
     */
    private function generateProofWithProgress(): void
    {
        // Simulate progress in steps
        $progressSteps = [10, 25, 50, 75, 90];
        $estimatedSeconds = $this->proofJob->estimated_seconds ?? 30;
        $stepDelay = (int) (($estimatedSeconds * 1000000) / count($progressSteps)); // microseconds

        foreach ($progressSteps as $progress) {
            if (! $this->proofJob->isInProgress()) {
                // Job was cancelled
                return;
            }

            // Simulate work
            usleep(min($stepDelay, 5000000)); // Max 5 seconds per step in demo

            // Update progress
            $this->proofJob->updateProgress($progress);

            // Broadcast progress update
            event(new DelegatedProofProgress($this->proofJob));

            Log::debug('Proof generation progress', [
                'job_id'   => $this->proofJob->id,
                'progress' => $progress,
            ]);
        }
    }

    /**
     * Generate a demo proof (deterministic based on inputs).
     */
    private function generateDemoProof(): string
    {
        // In production, this would call actual ZK proving infrastructure
        // For demo, generate a deterministic proof based on inputs

        $publicInputs = $this->proofJob->public_inputs;
        $proofType = $this->proofJob->proof_type;
        $network = $this->proofJob->network;

        // Create a deterministic "proof" from inputs
        $proofData = hash('sha256', json_encode([
            'type'    => $proofType,
            'network' => $network,
            'inputs'  => $publicInputs,
            'salt'    => 'demo_proof_' . $this->proofJob->id,
        ]) ?: '');

        // Return as hex-encoded proof structure
        return '0x' . $proofData . str_repeat('0', 256); // Pad to simulate full proof
    }

    /**
     * Handle proof generation failure.
     */
    private function handleFailure(Throwable $exception): void
    {
        $errorMessage = $exception->getMessage();

        Log::error('Delegated proof generation failed', [
            'job_id' => $this->proofJob->id,
            'error'  => $errorMessage,
            'trace'  => $exception->getTraceAsString(),
        ]);

        $this->proofJob->markFailed($errorMessage);

        // Broadcast failure
        event(new DelegatedProofFailed($this->proofJob));
    }
}
