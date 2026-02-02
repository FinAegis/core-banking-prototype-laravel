<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Events\DelegatedProofRequested;
use App\Domain\Privacy\Exceptions\DelegatedProofException;
use App\Domain\Privacy\Jobs\GenerateDelegatedProofJob;
use App\Domain\Privacy\Models\DelegatedProofJob;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing delegated proof generation.
 *
 * Handles proof requests from low-end mobile devices that cannot perform
 * client-side proof generation. Manages job lifecycle, queue dispatch,
 * and status tracking.
 */
class DelegatedProofService
{
    /**
     * Supported proof types for delegated generation.
     *
     * @var array<string, array{estimated_seconds: int, required_inputs: array<string>}>
     */
    private const PROOF_TYPES = [
        'shield_1_1' => [
            'estimated_seconds' => 30,
            'required_inputs'   => ['amount', 'token', 'recipient_commitment'],
        ],
        'unshield_2_1' => [
            'estimated_seconds' => 25,
            'required_inputs'   => ['nullifier', 'merkle_path', 'merkle_root'],
        ],
        'transfer_2_2' => [
            'estimated_seconds' => 45,
            'required_inputs'   => ['sender_nullifier', 'recipient_commitment', 'merkle_path'],
        ],
        'proof_of_innocence' => [
            'estimated_seconds' => 20,
            'required_inputs'   => ['nullifier_set_hash', 'exclusion_proof'],
        ],
    ];

    /**
     * Supported networks for delegated proving.
     */
    private const SUPPORTED_NETWORKS = ['polygon', 'base', 'arbitrum'];

    /**
     * Request a new delegated proof generation.
     *
     * @param array<string, mixed> $publicInputs
     * @throws DelegatedProofException
     */
    public function requestProof(
        User $user,
        string $proofType,
        string $network,
        array $publicInputs,
        string $encryptedPrivateInputs
    ): DelegatedProofJob {
        $this->validateRequest($proofType, $network, $publicInputs);
        $this->checkQueueCapacity($user);

        $estimatedSeconds = self::PROOF_TYPES[$proofType]['estimated_seconds'];

        $job = DelegatedProofJob::create([
            'user_id'                  => $user->id,
            'proof_type'               => $proofType,
            'network'                  => $network,
            'public_inputs'            => $publicInputs,
            'encrypted_private_inputs' => $encryptedPrivateInputs,
            'status'                   => DelegatedProofJob::STATUS_QUEUED,
            'progress'                 => 0,
            'estimated_seconds'        => $estimatedSeconds,
        ]);

        Log::info('Delegated proof requested', [
            'job_id'     => $job->id,
            'user_id'    => $user->id,
            'proof_type' => $proofType,
            'network'    => $network,
        ]);

        // Dispatch the background job
        GenerateDelegatedProofJob::dispatch($job);

        // Broadcast the request event
        event(new DelegatedProofRequested($job));

        return $job;
    }

    /**
     * Get a proof job by ID for a specific user.
     *
     * @throws DelegatedProofException
     */
    public function getJob(User $user, string $jobId): DelegatedProofJob
    {
        $job = DelegatedProofJob::where('id', $jobId)
            ->where('user_id', $user->id)
            ->first();

        if (! $job) {
            throw DelegatedProofException::jobNotFound($jobId);
        }

        return $job;
    }

    /**
     * Get all proof jobs for a user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DelegatedProofJob>
     */
    public function getUserJobs(User $user, ?string $status = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = DelegatedProofJob::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Cancel a pending proof job.
     *
     * @throws DelegatedProofException
     */
    public function cancelJob(User $user, string $jobId): void
    {
        $job = $this->getJob($user, $jobId);

        if (! $job->isInProgress()) {
            throw DelegatedProofException::cannotCancel($jobId, $job->status);
        }

        $job->markFailed('Cancelled by user');

        Log::info('Delegated proof cancelled', [
            'job_id'  => $job->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Get supported proof types.
     *
     * @return array<string, array{estimated_seconds: int, required_inputs: array<string>}>
     */
    public function getSupportedProofTypes(): array
    {
        return self::PROOF_TYPES;
    }

    /**
     * Get supported networks for delegated proving.
     *
     * @return array<string>
     */
    public function getSupportedNetworks(): array
    {
        return self::SUPPORTED_NETWORKS;
    }

    /**
     * Validate the proof request.
     *
     * @param array<string, mixed> $publicInputs
     * @throws DelegatedProofException
     */
    private function validateRequest(string $proofType, string $network, array $publicInputs): void
    {
        if (! isset(self::PROOF_TYPES[$proofType])) {
            throw DelegatedProofException::unsupportedProofType(
                $proofType,
                array_keys(self::PROOF_TYPES)
            );
        }

        if (! in_array($network, self::SUPPORTED_NETWORKS, true)) {
            throw DelegatedProofException::unsupportedNetwork(
                $network,
                self::SUPPORTED_NETWORKS
            );
        }

        // Validate required inputs are present
        $requiredInputs = self::PROOF_TYPES[$proofType]['required_inputs'];
        $missingInputs = array_diff($requiredInputs, array_keys($publicInputs));

        if (! empty($missingInputs)) {
            throw DelegatedProofException::missingInputs($missingInputs);
        }
    }

    /**
     * Check if the user has capacity for more proof jobs.
     *
     * @throws DelegatedProofException
     */
    private function checkQueueCapacity(User $user): void
    {
        $maxQueueSize = (int) config('privacy.delegated_proving.max_queue_size', 100);

        $pendingCount = DelegatedProofJob::where('user_id', $user->id)
            ->whereIn('status', [DelegatedProofJob::STATUS_QUEUED, DelegatedProofJob::STATUS_PROVING])
            ->count();

        if ($pendingCount >= $maxQueueSize) {
            throw DelegatedProofException::queueFull($maxQueueSize);
        }
    }
}
