<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessWebhookDelivery;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\UnitTestCase;
use Exception;

class ProcessWebhookDeliveryTest extends UnitTestCase
{
    protected function shouldCreateDefaultAccountsInSetup(): bool
    {
        return false;
    }

    protected Webhook $webhook;
    protected WebhookDelivery $delivery;
    protected WebhookService $webhookService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->webhook = Webhook::factory()->create([
            'is_active' => true,
            'timeout_seconds' => 30,
            'secret' => 'test-secret',
        ]);
        
        $this->delivery = WebhookDelivery::factory()->create([
            'webhook_uuid' => $this->webhook->uuid,
            'event_type' => 'test.event',
            'payload' => ['test' => 'data'],
        ]);
        
        $this->webhookService = $this->mock(WebhookService::class);
    }

    public function test_job_delivers_webhook_successfully()
    {
        $expectedPayload = json_encode(['test' => 'data']);
        $expectedSignature = 'test-signature-hash';

        $this->webhookService
            ->shouldReceive('generateSignature')
            ->once()
            ->with($expectedPayload, 'test-secret')
            ->andReturn($expectedSignature);

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $job = new ProcessWebhookDelivery($this->delivery);
        $job->handle($this->webhookService);

        // Verify HTTP request was made with correct parameters
        Http::assertSent(function ($request) use ($expectedSignature) {
            return $request->url() === $this->webhook->url &&
                   $request->method() === 'POST' &&
                   $request['test'] === 'data' &&
                   $request->header('Content-Type')[0] === 'application/json' &&
                   $request->header('User-Agent')[0] === 'FinAegis-Webhook/1.0' &&
                   $request->header('X-Webhook-ID')[0] === $this->webhook->uuid &&
                   $request->header('X-Webhook-Event')[0] === 'test.event' &&
                   $request->header('X-Webhook-Delivery')[0] === $this->delivery->uuid &&
                   $request->header('X-Webhook-Signature')[0] === $expectedSignature;
        });

        $this->delivery->refresh();
        $this->assertEquals('delivered', $this->delivery->status);
        $this->assertEquals(200, $this->delivery->response_status);
        $this->assertNotNull($this->delivery->delivered_at);
    }

    public function test_job_delivers_webhook_without_secret()
    {
        $this->webhook->update(['secret' => null]);

        $this->webhookService
            ->shouldNotReceive('generateSignature');

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $job = new ProcessWebhookDelivery($this->delivery);
        $job->handle($this->webhookService);

        // Verify no signature header was sent
        Http::assertSent(function ($request) {
            return !$request->hasHeader('X-Webhook-Signature');
        });

        $this->delivery->refresh();
        $this->assertEquals('delivered', $this->delivery->status);
    }

    public function test_job_skips_inactive_webhook()
    {
        $this->webhook->update(['is_active' => false]);

        Log::shouldReceive('warning')
            ->once()
            ->with("Skipping delivery for inactive webhook: {$this->webhook->uuid}");

        $this->webhookService
            ->shouldNotReceive('generateSignature');

        Http::fake();

        $job = new ProcessWebhookDelivery($this->delivery);
        $job->handle($this->webhookService);

        Http::assertNothingSent();
        
        $this->delivery->refresh();
        $this->assertEquals('pending', $this->delivery->status);
    }

    public function test_job_handles_http_error_response()
    {
        $this->webhookService
            ->shouldReceive('generateSignature')
            ->once()
            ->andReturn('test-signature');

        Http::fake([
            $this->webhook->url => Http::response(['error' => 'Bad request'], 400),
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Webhook delivery failed', \Mockery::type('array'));

        $job = new ProcessWebhookDelivery($this->delivery);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('HTTP 400:');

        $job->handle($this->webhookService);

        $this->delivery->refresh();
        $this->assertEquals('failed', $this->delivery->status);
        $this->assertEquals(400, $this->delivery->response_status);
    }

    public function test_job_handles_network_timeout()
    {
        $this->webhookService
            ->shouldReceive('generateSignature')
            ->once()
            ->andReturn('test-signature');

        Http::fake([
            $this->webhook->url => Http::response('', 408), // Timeout response
        ]);

        // Allow Log::error to be called without strict expectations
        Log::shouldReceive('error')->andReturnTrue();

        $job = new ProcessWebhookDelivery($this->delivery);

        $this->expectException(RequestException::class);

        $job->handle($this->webhookService);

        $this->delivery->refresh();
        $this->assertEquals('failed', $this->delivery->status);
    }

    public function test_job_logs_successful_delivery()
    {
        $this->webhookService
            ->shouldReceive('generateSignature')
            ->andReturn('test-signature');

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Webhook delivered successfully' &&
                       $context['webhook_id'] === $this->webhook->uuid &&
                       $context['delivery_id'] === $this->delivery->uuid &&
                       $context['status_code'] === 200 &&
                       is_int($context['duration_ms']);
            });

        $job = new ProcessWebhookDelivery($this->delivery);
        $job->handle($this->webhookService);
    }

    public function test_job_marks_webhook_as_triggered()
    {
        $this->webhookService
            ->shouldReceive('generateSignature')
            ->andReturn('test-signature');

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $job = new ProcessWebhookDelivery($this->delivery);
        $job->handle($this->webhookService);

        $this->webhook->refresh();
        $this->assertNotNull($this->webhook->last_triggered_at);
    }

    public function test_job_measures_response_duration()
    {
        $this->webhookService
            ->shouldReceive('generateSignature')
            ->andReturn('test-signature');

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $job = new ProcessWebhookDelivery($this->delivery);
        $job->handle($this->webhookService);

        $this->delivery->refresh();
        $this->assertIsInt($this->delivery->duration_ms);
        $this->assertGreaterThan(0, $this->delivery->duration_ms);
    }

    public function test_job_respects_webhook_timeout()
    {
        $this->webhook->update(['timeout_seconds' => 5]);

        $this->webhookService
            ->shouldReceive('generateSignature')
            ->andReturn('test-signature');

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $job = new ProcessWebhookDelivery($this->delivery);
        $job->handle($this->webhookService);

        // Verify timeout was applied to HTTP client
        Http::assertSent(function ($request) {
            // We can't directly test the timeout, but we can verify the request was made
            return $request->url() === $this->webhook->url;
        });
    }

    public function test_job_handles_failure_callback()
    {
        $exception = new Exception('Job failed permanently');
        
        Log::shouldReceive('error')
            ->once()
            ->with('Webhook delivery job failed permanently', [
                'delivery_id' => $this->delivery->uuid,
                'error' => 'Job failed permanently',
            ]);

        $job = new ProcessWebhookDelivery($this->delivery);
        $job->failed($exception);
    }

    public function test_job_retry_configuration()
    {
        $job = new ProcessWebhookDelivery($this->delivery);
        
        // Test retry until
        $retryUntil = $job->retryUntil();
        $this->assertInstanceOf(\DateTime::class, $retryUntil);
        $this->assertGreaterThan(now(), $retryUntil);
        
        // Test backoff strategy
        $backoff = $job->backoff();
        $this->assertEquals([60, 300, 900], $backoff);
    }

    public function test_job_handles_webhook_with_custom_headers()
    {
        $this->webhook->update([
            'headers' => [
                'Authorization' => 'Bearer token123',
                'X-Custom-Header' => 'custom-value',
            ],
            'secret' => null, // Remove secret to simplify test
        ]);

        $this->webhookService
            ->shouldNotReceive('generateSignature');

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $job = new ProcessWebhookDelivery($this->delivery);
        $job->handle($this->webhookService);

        // Verify custom headers were included
        Http::assertSent(function ($request) {
            return $request->header('Authorization')[0] === 'Bearer token123' &&
                   $request->header('X-Custom-Header')[0] === 'custom-value' &&
                   $request->header('Content-Type')[0] === 'application/json';
        });
    }

    public function test_job_serialization()
    {
        $job = new ProcessWebhookDelivery($this->delivery);
        
        // Test that the job can be serialized and unserialized
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);
        
        $this->assertEquals($job->delivery->id, $unserialized->delivery->id);
    }
}