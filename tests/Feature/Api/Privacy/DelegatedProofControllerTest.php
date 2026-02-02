<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Privacy;

use App\Domain\Privacy\Models\DelegatedProofJob;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DelegatedProofControllerTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write'])->plainTextToken;
    }

    public function test_get_supported_types_returns_proof_types(): void
    {
        $response = $this->getJson('/api/v1/privacy/delegated-proof-types');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'proof_types' => [
                        'shield_1_1',
                        'unshield_2_1',
                        'transfer_2_2',
                        'proof_of_innocence',
                    ],
                    'networks',
                ],
            ]);
    }

    public function test_request_proof_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/privacy/delegated-proof', [
            'proof_type'               => 'shield_1_1',
            'network'                  => 'polygon',
            'public_inputs'            => ['amount' => '1000000'],
            'encrypted_private_inputs' => 'encrypted_data',
        ]);

        $response->assertUnauthorized();
    }

    public function test_request_proof_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/delegated-proof', []);

        $response->assertUnprocessable();
    }

    public function test_request_proof_validates_proof_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/delegated-proof', [
                'proof_type'               => 'invalid_type',
                'network'                  => 'polygon',
                'public_inputs'            => ['amount' => '1000000'],
                'encrypted_private_inputs' => 'encrypted_data',
            ]);

        $response->assertUnprocessable();
    }

    public function test_request_proof_validates_network(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/delegated-proof', [
                'proof_type'               => 'shield_1_1',
                'network'                  => 'invalid_network',
                'public_inputs'            => ['amount' => '1000000'],
                'encrypted_private_inputs' => 'encrypted_data',
            ]);

        $response->assertUnprocessable();
    }

    public function test_request_proof_creates_job(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/delegated-proof', [
                'proof_type'    => 'shield_1_1',
                'network'       => 'polygon',
                'public_inputs' => [
                    'amount'               => '1000000',
                    'token'                => 'USDC',
                    'recipient_commitment' => '0x' . str_repeat('a', 64),
                ],
                'encrypted_private_inputs' => 'encrypted_data_here',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'job_id',
                    'status',
                    'estimated_seconds',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals('queued', $data['status']);
    }

    public function test_request_proof_returns_error_for_missing_inputs(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/delegated-proof', [
                'proof_type'               => 'shield_1_1',
                'network'                  => 'polygon',
                'public_inputs'            => ['amount' => '1000000'], // Missing token and recipient_commitment
                'encrypted_private_inputs' => 'encrypted_data',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'ERR_PRIVACY_313');
    }

    public function test_get_proof_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/privacy/delegated-proof/some-job-id');

        $response->assertUnauthorized();
    }

    public function test_get_proof_status_returns_job_details(): void
    {
        // Create a job first
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/delegated-proof', [
                'proof_type'    => 'shield_1_1',
                'network'       => 'polygon',
                'public_inputs' => [
                    'amount'               => '1000000',
                    'token'                => 'USDC',
                    'recipient_commitment' => '0x' . str_repeat('a', 64),
                ],
                'encrypted_private_inputs' => 'encrypted_data',
            ]);

        $jobId = $createResponse->json('data.job_id');

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/privacy/delegated-proof/{$jobId}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'proof_type',
                    'network',
                    'status',
                    'progress',
                    'estimated_seconds',
                ],
            ]);
    }

    public function test_get_proof_status_returns_404_for_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/delegated-proof/non-existent-id');

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'ERR_PRIVACY_310');
    }

    public function test_get_proof_status_does_not_return_other_user_jobs(): void
    {
        // Create a job as first user
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/delegated-proof', [
                'proof_type'    => 'shield_1_1',
                'network'       => 'polygon',
                'public_inputs' => [
                    'amount'               => '1000000',
                    'token'                => 'USDC',
                    'recipient_commitment' => '0x' . str_repeat('a', 64),
                ],
                'encrypted_private_inputs' => 'encrypted_data',
            ]);

        $jobId = $createResponse->json('data.job_id');

        // Try to access as other user
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser, ['read', 'write']);

        $response = $this->getJson("/api/v1/privacy/delegated-proof/{$jobId}");

        $response->assertNotFound();
    }

    public function test_list_proof_jobs_returns_user_jobs(): void
    {
        // Create multiple jobs
        $this->withToken($this->token)
            ->postJson('/api/v1/privacy/delegated-proof', [
                'proof_type'    => 'shield_1_1',
                'network'       => 'polygon',
                'public_inputs' => [
                    'amount'               => '1000000',
                    'token'                => 'USDC',
                    'recipient_commitment' => '0x' . str_repeat('a', 64),
                ],
                'encrypted_private_inputs' => 'encrypted_data_1',
            ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/privacy/delegated-proof', [
                'proof_type'    => 'unshield_2_1',
                'network'       => 'base',
                'public_inputs' => [
                    'nullifier'   => '0x' . str_repeat('b', 64),
                    'merkle_path' => [],
                    'merkle_root' => '0x' . str_repeat('c', 64),
                ],
                'encrypted_private_inputs' => 'encrypted_data_2',
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/delegated-proofs');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_proof_jobs_filters_by_status(): void
    {
        // Create a job
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/delegated-proof', [
                'proof_type'    => 'shield_1_1',
                'network'       => 'polygon',
                'public_inputs' => [
                    'amount'               => '1000000',
                    'token'                => 'USDC',
                    'recipient_commitment' => '0x' . str_repeat('a', 64),
                ],
                'encrypted_private_inputs' => 'encrypted_data',
            ]);

        // Mark one as completed directly
        $jobId = $createResponse->json('data.job_id');
        /** @var DelegatedProofJob $job */
        $job = DelegatedProofJob::find($jobId);
        $job->markCompleted('0x' . str_repeat('d', 128));

        // Filter by status
        $queuedResponse = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/delegated-proofs?status=queued');

        $completedResponse = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/delegated-proofs?status=completed');

        $queuedResponse->assertOk()->assertJsonCount(0, 'data');
        $completedResponse->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cancel_proof_job_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/v1/privacy/delegated-proof/some-job-id');

        $response->assertUnauthorized();
    }

    public function test_cancel_proof_job_cancels_queued_job(): void
    {
        // Create a job
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/delegated-proof', [
                'proof_type'    => 'shield_1_1',
                'network'       => 'polygon',
                'public_inputs' => [
                    'amount'               => '1000000',
                    'token'                => 'USDC',
                    'recipient_commitment' => '0x' . str_repeat('a', 64),
                ],
                'encrypted_private_inputs' => 'encrypted_data',
            ]);

        $jobId = $createResponse->json('data.job_id');

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v1/privacy/delegated-proof/{$jobId}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Verify the job is failed
        /** @var DelegatedProofJob $job */
        $job = DelegatedProofJob::find($jobId);
        $this->assertEquals(DelegatedProofJob::STATUS_FAILED, $job->status);
    }

    public function test_cancel_proof_job_returns_error_for_completed_job(): void
    {
        // Create and complete a job
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/delegated-proof', [
                'proof_type'    => 'shield_1_1',
                'network'       => 'polygon',
                'public_inputs' => [
                    'amount'               => '1000000',
                    'token'                => 'USDC',
                    'recipient_commitment' => '0x' . str_repeat('a', 64),
                ],
                'encrypted_private_inputs' => 'encrypted_data',
            ]);

        $jobId = $createResponse->json('data.job_id');
        /** @var DelegatedProofJob $job */
        $job = DelegatedProofJob::find($jobId);
        $job->markCompleted('0x' . str_repeat('d', 128));

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v1/privacy/delegated-proof/{$jobId}");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'ERR_PRIVACY_315');
    }
}
