<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Connectors;

use App\Domain\Custodian\Contracts\ICustodianConnector;
use App\Domain\Custodian\Services\CircuitBreakerService;
use App\Domain\Custodian\Services\RetryService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;

abstract class BaseCustodianConnector implements ICustodianConnector
{
    protected array $config;
    protected PendingRequest $client;
    protected CircuitBreakerService $circuitBreaker;
    protected RetryService $retryService;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeClient();
        $this->initializeResilience();
    }

    protected function initializeClient(): void
    {
        $this->client = Http::baseUrl($this->config['base_url'] ?? '')
            ->timeout($this->config['timeout'] ?? 30)
            ->withHeaders($this->getHeaders());

        if ($this->config['verify_ssl'] ?? true) {
            $this->client->withOptions(['verify' => true]);
        }
    }

    protected function getHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if (isset($this->config['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $this->config['api_key'];
        }

        return array_merge($headers, $this->config['headers'] ?? []);
    }

    public function getName(): string
    {
        return $this->config['name'] ?? class_basename($this);
    }

    public function isAvailable(): bool
    {
        try {
            // Use executeWithResilience directly since we need to handle non-Response return
            return $this->executeWithResilience(
                serviceIdentifier: "GET:{$this->getHealthCheckEndpoint()}",
                operation: function () {
                    $response = $this->client->get($this->getHealthCheckEndpoint());
                    return $response->successful();
                },
                fallback: function () {
                    // If health check fails, return cached status or false
                    Log::warning("Custodian {$this->getName()} health check using fallback");
                    return false;
                }
            );
        } catch (\Exception $e) {
            Log::error("Custodian {$this->getName()} health check failed", [
                'error' => $e->getMessage(),
                'circuit_breaker_state' => $this->circuitBreaker->getState("{$this->getName()}.GET:{$this->getHealthCheckEndpoint()}"),
            ]);
            return false;
        }
    }

    protected function logRequest(string $method, string $endpoint, array $data = []): void
    {
        if ($this->config['debug'] ?? false) {
            Log::debug("Custodian {$this->getName()} request", [
                'method' => $method,
                'endpoint' => $endpoint,
                'data' => $data,
            ]);
        }
    }

    protected function logResponse(string $method, string $endpoint, $response): void
    {
        if ($this->config['debug'] ?? false) {
            Log::debug("Custodian {$this->getName()} response", [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }
    }

    protected function initializeResilience(): void
    {
        $resilienceConfig = config('custodians.resilience');
        
        // Initialize circuit breaker with configuration
        $this->circuitBreaker = new CircuitBreakerService(
            failureThreshold: $resilienceConfig['circuit_breaker']['failure_threshold'] ?? 5,
            successThreshold: $resilienceConfig['circuit_breaker']['success_threshold'] ?? 2,
            timeout: $resilienceConfig['circuit_breaker']['timeout'] ?? 60,
            failureRateThreshold: $resilienceConfig['circuit_breaker']['failure_rate_threshold'] ?? 0.5,
            sampleSize: $resilienceConfig['circuit_breaker']['sample_size'] ?? 10
        );
        
        // Initialize retry service with configuration
        $this->retryService = new RetryService(
            maxAttempts: $resilienceConfig['retry']['max_attempts'] ?? 3,
            initialDelayMs: $resilienceConfig['retry']['initial_delay_ms'] ?? 200,
            maxDelayMs: $resilienceConfig['retry']['max_delay_ms'] ?? 5000,
            multiplier: $resilienceConfig['retry']['multiplier'] ?? 2.5,
            jitter: $resilienceConfig['retry']['jitter'] ?? true
        );
    }

    /**
     * Execute API request with circuit breaker and retry logic
     */
    protected function executeWithResilience(
        string $serviceIdentifier,
        callable $operation,
        ?callable $fallback = null
    ): mixed {
        // Execute with circuit breaker protection
        return $this->circuitBreaker->execute(
            service: "{$this->getName()}.{$serviceIdentifier}",
            operation: function () use ($operation, $serviceIdentifier) {
                // Execute with retry logic
                return $this->retryService->execute(
                    operation: $operation,
                    retryableExceptions: [
                        ConnectionException::class,
                        RequestException::class,
                    ],
                    context: "{$this->getName()}.{$serviceIdentifier}"
                );
            },
            fallback: $fallback
        );
    }

    /**
     * Make resilient API request
     */
    protected function resilientApiRequest(
        string $method,
        string $endpoint,
        array $data = [],
        ?callable $fallback = null
    ): \Illuminate\Http\Client\Response {
        return $this->executeWithResilience(
            serviceIdentifier: "{$method}:{$endpoint}",
            operation: function () use ($method, $endpoint, $data) {
                $this->logRequest($method, $endpoint, $data);
                
                $response = match (strtoupper($method)) {
                    'GET' => $this->client->get($endpoint, $data),
                    'POST' => $this->client->post($endpoint, $data),
                    'PUT' => $this->client->put($endpoint, $data),
                    'DELETE' => $this->client->delete($endpoint),
                    default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
                };
                
                $this->logResponse($method, $endpoint, $response);
                
                // Throw exception for non-successful responses to trigger retry
                if (!$response->successful()) {
                    throw new RequestException($response);
                }
                
                return $response;
            },
            fallback: $fallback
        );
    }

    /**
     * Get circuit breaker metrics for this connector
     */
    public function getCircuitBreakerMetrics(string $operation = ''): array
    {
        $service = $operation ? "{$this->getName()}.{$operation}" : $this->getName();
        return $this->circuitBreaker->getMetrics($service);
    }

    /**
     * Reset circuit breaker for specific operation or all operations
     */
    public function resetCircuitBreaker(string $operation = ''): void
    {
        $service = $operation ? "{$this->getName()}.{$operation}" : $this->getName();
        $this->circuitBreaker->reset($service);
    }

    abstract protected function getHealthCheckEndpoint(): string;
}