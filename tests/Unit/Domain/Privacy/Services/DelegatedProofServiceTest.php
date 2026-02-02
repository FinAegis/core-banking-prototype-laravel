<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Privacy\Services;

use App\Domain\Privacy\Exceptions\DelegatedProofException;
use App\Domain\Privacy\Models\DelegatedProofJob;
use App\Domain\Privacy\Services\DelegatedProofService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DelegatedProofServiceTest extends TestCase
{
    private DelegatedProofService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();

        $this->service = new DelegatedProofService();
        $this->user = User::factory()->create();
    }

    public function test_get_supported_proof_types(): void
    {
        $proofTypes = $this->service->getSupportedProofTypes();

        $this->assertIsArray($proofTypes);
        $this->assertArrayHasKey('shield_1_1', $proofTypes);
        $this->assertArrayHasKey('unshield_2_1', $proofTypes);
        $this->assertArrayHasKey('transfer_2_2', $proofTypes);
        $this->assertArrayHasKey('proof_of_innocence', $proofTypes);
    }

    public function test_get_supported_networks(): void
    {
        $networks = $this->service->getSupportedNetworks();

        $this->assertIsArray($networks);
        $this->assertContains('polygon', $networks);
        $this->assertContains('base', $networks);
        $this->assertContains('arbitrum', $networks);
    }

    public function test_request_proof_creates_job(): void
    {
        $job = $this->service->requestProof(
            $this->user,
            'shield_1_1',
            'polygon',
            [
                'amount'               => '1000000',
                'token'                => 'USDC',
                'recipient_commitment' => '0x' . str_repeat('a', 64),
            ],
            'encrypted_data_here'
        );

        $this->assertInstanceOf(DelegatedProofJob::class, $job);
        $this->assertEquals('shield_1_1', $job->proof_type);
        $this->assertEquals('polygon', $job->network);
        $this->assertEquals(DelegatedProofJob::STATUS_QUEUED, $job->status);
        $this->assertEquals(0, $job->progress);
        $this->assertNotNull($job->estimated_seconds);
    }

    public function test_request_proof_throws_for_invalid_proof_type(): void
    {
        $this->expectException(DelegatedProofException::class);
        $this->expectExceptionMessage('Unsupported proof type');

        $this->service->requestProof(
            $this->user,
            'invalid_type',
            'polygon',
            [],
            'encrypted_data'
        );
    }

    public function test_request_proof_throws_for_invalid_network(): void
    {
        $this->expectException(DelegatedProofException::class);
        $this->expectExceptionMessage('Unsupported network');

        $this->service->requestProof(
            $this->user,
            'shield_1_1',
            'ethereum', // Not supported for delegated proving
            [
                'amount'               => '1000000',
                'token'                => 'USDC',
                'recipient_commitment' => '0x' . str_repeat('a', 64),
            ],
            'encrypted_data'
        );
    }

    public function test_request_proof_throws_for_missing_inputs(): void
    {
        $this->expectException(DelegatedProofException::class);
        $this->expectExceptionMessage('Missing required public inputs');

        $this->service->requestProof(
            $this->user,
            'shield_1_1',
            'polygon',
            ['amount' => '1000000'], // Missing token and recipient_commitment
            'encrypted_data'
        );
    }

    public function test_get_job_returns_job(): void
    {
        $createdJob = $this->service->requestProof(
            $this->user,
            'shield_1_1',
            'polygon',
            [
                'amount'               => '1000000',
                'token'                => 'USDC',
                'recipient_commitment' => '0x' . str_repeat('a', 64),
            ],
            'encrypted_data'
        );

        $retrievedJob = $this->service->getJob($this->user, $createdJob->id);

        $this->assertEquals($createdJob->id, $retrievedJob->id);
    }

    public function test_get_job_throws_for_not_found(): void
    {
        $this->expectException(DelegatedProofException::class);
        $this->expectExceptionMessage('not found');

        $this->service->getJob($this->user, 'non-existent-id');
    }

    public function test_get_job_throws_for_other_user(): void
    {
        $createdJob = $this->service->requestProof(
            $this->user,
            'shield_1_1',
            'polygon',
            [
                'amount'               => '1000000',
                'token'                => 'USDC',
                'recipient_commitment' => '0x' . str_repeat('a', 64),
            ],
            'encrypted_data'
        );

        $otherUser = User::factory()->create();

        $this->expectException(DelegatedProofException::class);

        $this->service->getJob($otherUser, $createdJob->id);
    }

    public function test_get_user_jobs_returns_only_user_jobs(): void
    {
        // Create jobs for first user
        $this->service->requestProof(
            $this->user,
            'shield_1_1',
            'polygon',
            [
                'amount'               => '1000000',
                'token'                => 'USDC',
                'recipient_commitment' => '0x' . str_repeat('a', 64),
            ],
            'encrypted_data'
        );

        // Create jobs for other user
        $otherUser = User::factory()->create();
        $this->service->requestProof(
            $otherUser,
            'unshield_2_1',
            'base',
            [
                'nullifier'   => '0x' . str_repeat('b', 64),
                'merkle_path' => [],
                'merkle_root' => '0x' . str_repeat('c', 64),
            ],
            'other_encrypted_data'
        );

        $userJobs = $this->service->getUserJobs($this->user);

        $this->assertCount(1, $userJobs);
        $firstJob = $userJobs->first();
        $this->assertNotNull($firstJob);
        $this->assertEquals('shield_1_1', $firstJob->proof_type);
    }

    public function test_get_user_jobs_filters_by_status(): void
    {
        $job1 = $this->service->requestProof(
            $this->user,
            'shield_1_1',
            'polygon',
            [
                'amount'               => '1000000',
                'token'                => 'USDC',
                'recipient_commitment' => '0x' . str_repeat('a', 64),
            ],
            'encrypted_data'
        );

        $job2 = $this->service->requestProof(
            $this->user,
            'unshield_2_1',
            'base',
            [
                'nullifier'   => '0x' . str_repeat('b', 64),
                'merkle_path' => [],
                'merkle_root' => '0x' . str_repeat('c', 64),
            ],
            'encrypted_data_2'
        );

        // Mark one as completed
        $job2->markCompleted('0x' . str_repeat('d', 128));

        $queuedJobs = $this->service->getUserJobs($this->user, 'queued');
        $completedJobs = $this->service->getUserJobs($this->user, 'completed');

        $this->assertCount(1, $queuedJobs);
        $this->assertCount(1, $completedJobs);
    }

    public function test_cancel_job_marks_as_failed(): void
    {
        $job = $this->service->requestProof(
            $this->user,
            'shield_1_1',
            'polygon',
            [
                'amount'               => '1000000',
                'token'                => 'USDC',
                'recipient_commitment' => '0x' . str_repeat('a', 64),
            ],
            'encrypted_data'
        );

        $this->service->cancelJob($this->user, $job->id);

        $job->refresh();
        $this->assertEquals(DelegatedProofJob::STATUS_FAILED, $job->status);
        $this->assertEquals('Cancelled by user', $job->error);
    }

    public function test_cancel_job_throws_for_completed_job(): void
    {
        $job = $this->service->requestProof(
            $this->user,
            'shield_1_1',
            'polygon',
            [
                'amount'               => '1000000',
                'token'                => 'USDC',
                'recipient_commitment' => '0x' . str_repeat('a', 64),
            ],
            'encrypted_data'
        );

        $job->markCompleted('0x' . str_repeat('a', 128));

        $this->expectException(DelegatedProofException::class);
        $this->expectExceptionMessage('Cannot cancel');

        $this->service->cancelJob($this->user, $job->id);
    }
}
