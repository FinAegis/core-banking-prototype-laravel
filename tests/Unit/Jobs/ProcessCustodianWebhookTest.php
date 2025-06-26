<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessCustodianWebhook;
use App\Domain\Custodian\Services\WebhookProcessorService;
use App\Models\CustodianWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Exception;

class ProcessCustodianWebhookTest extends TestCase
{

    protected CustodianWebhook $webhook;
    protected WebhookProcessorService $processorService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->webhook = CustodianWebhook::factory()->create([
            'status' => 'pending',
        ]);
        
        $this->processorService = $this->mock(WebhookProcessorService::class);
    }

    public function test_job_processes_pending_webhook_successfully()
    {
        $this->processorService
            ->shouldReceive('process')
            ->once()
            ->with(\Mockery::on(function ($webhook) {
                return $webhook instanceof CustodianWebhook && 
                       $webhook->uuid === $this->webhook->uuid;
            }));

        $job = new ProcessCustodianWebhook($this->webhook->uuid);
        $job->handle($this->processorService);

        $this->webhook->refresh();
        $this->assertEquals('processed', $this->webhook->status);
    }

    public function test_job_processes_failed_webhook()
    {
        $this->webhook->update(['status' => 'failed']);
        
        $this->processorService
            ->shouldReceive('process')
            ->once()
            ->with(\Mockery::on(function ($webhook) {
                return $webhook instanceof CustodianWebhook && 
                       $webhook->uuid === $this->webhook->uuid;
            }));

        $job = new ProcessCustodianWebhook($this->webhook->uuid);
        $job->handle($this->processorService);

        $this->webhook->refresh();
        $this->assertEquals('processed', $this->webhook->status);
    }

    public function test_job_skips_already_processed_webhook()
    {
        $this->webhook->update(['status' => 'processed']);
        
        Log::shouldReceive('info')
            ->once()
            ->with('Webhook already processed', [
                'webhook_id' => $this->webhook->uuid,
                'status' => 'processed',
            ]);

        $this->processorService
            ->shouldNotReceive('process');

        $job = new ProcessCustodianWebhook($this->webhook->uuid);
        $job->handle($this->processorService);

        $this->webhook->refresh();
        $this->assertEquals('processed', $this->webhook->status);
    }

    public function test_job_skips_processing_webhook()
    {
        $this->webhook->update(['status' => 'processing']);
        
        Log::shouldReceive('info')
            ->once()
            ->with('Webhook already processed', [
                'webhook_id' => $this->webhook->uuid,
                'status' => 'processing',
            ]);

        $this->processorService
            ->shouldNotReceive('process');

        $job = new ProcessCustodianWebhook($this->webhook->uuid);
        $job->handle($this->processorService);

        $this->webhook->refresh();
        $this->assertEquals('processing', $this->webhook->status);
    }

    public function test_job_handles_nonexistent_webhook()
    {
        $nonExistentUuid = 'non-existent-uuid';
        
        Log::shouldReceive('error')
            ->once()
            ->with('Webhook not found', ['webhook_id' => $nonExistentUuid]);

        $this->processorService
            ->shouldNotReceive('process');

        $job = new ProcessCustodianWebhook($nonExistentUuid);
        $job->handle($this->processorService);
    }

    public function test_job_handles_processing_exception()
    {
        $exception = new Exception('Processing failed');
        
        $this->processorService
            ->shouldReceive('process')
            ->once()
            ->with(\Mockery::on(function ($webhook) {
                return $webhook instanceof CustodianWebhook && 
                       $webhook->uuid === $this->webhook->uuid;
            }))
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to process webhook', \Mockery::on(function ($data) {
                return isset($data['webhook_id']) && 
                       isset($data['error']) && 
                       $data['error'] === 'Processing failed';
            }));

        $job = new ProcessCustodianWebhook($this->webhook->uuid);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Processing failed');

        $job->handle($this->processorService);

        $this->webhook->refresh();
        $this->assertEquals('failed', $this->webhook->status);
        $this->assertEquals('Processing failed', $this->webhook->error_message);
    }

    public function test_job_logs_successful_processing()
    {
        $this->webhook->update([
            'custodian_name' => 'test-custodian',
            'event_type' => 'transaction.completed',
        ]);

        $this->processorService
            ->shouldReceive('process')
            ->once()
            ->with(\Mockery::on(function ($webhook) {
                return $webhook instanceof CustodianWebhook && 
                       $webhook->uuid === $this->webhook->uuid;
            }));

        Log::shouldReceive('info')
            ->once()
            ->with('Webhook processed successfully', [
                'webhook_id' => $this->webhook->id,
                'custodian' => 'test-custodian',
                'event_type' => 'transaction.completed',
            ]);

        $job = new ProcessCustodianWebhook($this->webhook->uuid);
        $job->handle($this->processorService);
    }

    public function test_job_handles_failure_callback()
    {
        $exception = new Exception('Job failed permanently');
        
        Log::shouldReceive('error')
            ->once()
            ->with('Webhook processing job failed permanently', [
                'webhook_id' => $this->webhook->uuid,
                'error' => 'Job failed permanently',
            ]);

        $job = new ProcessCustodianWebhook($this->webhook->uuid);
        $job->failed($exception);
    }

    public function test_job_configuration()
    {
        $job = new ProcessCustodianWebhook($this->webhook->uuid);
        
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout);
        $this->assertEquals('webhooks', $job->queue);
    }

    public function test_job_sets_webhook_to_processing_state()
    {
        $this->processorService
            ->shouldReceive('process')
            ->once()
            ->with(\Mockery::on(function ($webhook) {
                // Verify webhook was marked as processing during execution
                $this->assertEquals('processing', $webhook->status);
                return $webhook instanceof CustodianWebhook && 
                       $webhook->uuid === $this->webhook->uuid;
            }));

        $job = new ProcessCustodianWebhook($this->webhook->uuid);
        $job->handle($this->processorService);
    }

    public function test_job_serializable_properties()
    {
        $job = new ProcessCustodianWebhook($this->webhook->uuid);
        
        $this->assertEquals($this->webhook->uuid, $job->webhookId);
        
        // Test that the job can be serialized and unserialized
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);
        
        $this->assertEquals($job->webhookId, $unserialized->webhookId);
        $this->assertEquals($job->tries, $unserialized->tries);
        $this->assertEquals($job->timeout, $unserialized->timeout);
    }
}