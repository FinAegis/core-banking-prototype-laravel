<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Providers;

use App\Domain\Exchange\Contracts\IExchangeRateProvider;
use App\Domain\Exchange\Exceptions\RateLimitException;
use App\Domain\Exchange\Exceptions\RateProviderException;
use Closure;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseExchangeRateProvider implements IExchangeRateProvider
{
    protected array $config;

    protected PendingRequest $client;

    protected string $cachePrefix = 'exchange_rate_provider';

    protected int $cacheMinutes = 5;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeClient();
    }

    protected function initializeClient(): void
    {
        $this->client = Http::baseUrl($this->getBaseUrl())
            ->timeout($this->config['timeout'] ?? 30)
            ->withHeaders($this->getHeaders());

        if ($this->config['verify_ssl'] ?? true) {
            $this->client->withOptions(['verify' => true]);
        }
    }

    protected function getHeaders(): array
    {
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        // Add API key if configured
        if (isset($this->config['api_key'])) {
            $headers[$this->getApiKeyHeader()] = $this->config['api_key'];
        }

        return array_merge($headers, $this->config['headers'] ?? []);
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->client->get($this->getHealthCheckEndpoint());

            return $response->successful();
        } catch (Exception $e) {
            Log::error(
                "Exchange rate provider {$this->getName()} health check failed",
                [
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    public function supportsPair(string $fromCurrency, string $toCurrency): bool
    {
        $supported = $this->getSupportedCurrencies();

        return in_array($fromCurrency, $supported) && in_array($toCurrency, $supported);
    }

    /**
     * Check rate limit.
     */
    protected function checkRateLimit(): void
    {
        $key = "{$this->cachePrefix}:rate_limit:{$this->getName()}";
        $limit = $this->getCapabilities()->rateLimitPerMinute;

        $current = Cache::get($key, 0);

        if ($current >= $limit) {
            throw new RateLimitException(
                "Rate limit exceeded for {$this->getName()}. Limit: {$limit}/min"
            );
        }

        Cache::put($key, $current + 1, 60);
    }

    /**
     * Cache a value with provider-specific key.
     */
    protected function cache(string $key, mixed $value, ?int $minutes = null): void
    {
        $fullKey = "{$this->cachePrefix}:{$this->getName()}:{$key}";
        Cache::put($fullKey, $value, ($minutes ?? $this->cacheMinutes) * 60);
    }

    /**
     * Get cached value.
     */
    protected function getCached(string $key): mixed
    {
        $fullKey = "{$this->cachePrefix}:{$this->getName()}:{$key}";

        return Cache::get($fullKey);
    }

    /**
     * Remember value in cache.
     */
    protected function remember(string $key, Closure $callback, ?int $minutes = null): mixed
    {
        $fullKey = "{$this->cachePrefix}:{$this->getName()}:{$key}";

        return Cache::remember($fullKey, ($minutes ?? $this->cacheMinutes) * 60, $callback);
    }

    /**
     * Log API request.
     */
    protected function logRequest(string $method, string $endpoint, array $data = []): void
    {
        if ($this->config['debug'] ?? false) {
            Log::debug(
                "Exchange rate provider {$this->getName()} request",
                [
                    'method'   => $method,
                    'endpoint' => $endpoint,
                    'data'     => $data,
                ]
            );
        }
    }

    /**
     * Log API response.
     */
    protected function logResponse(string $method, string $endpoint, $response): void
    {
        if ($this->config['debug'] ?? false) {
            Log::debug(
                "Exchange rate provider {$this->getName()} response",
                [
                    'method'   => $method,
                    'endpoint' => $endpoint,
                    'status'   => $response->status(),
                    'body'     => $response->json(),
                ]
            );
        }
    }

    /**
     * Handle API errors.
     */
    protected function handleApiError(\Illuminate\Http\Client\Response $response, string $operation): void
    {
        $status = $response->status();
        $body = $response->json() ?? $response->body();

        $message = "Exchange rate provider {$this->getName()} {$operation} failed";

        if ($status === 429) {
            throw new RateLimitException("{$message}: Rate limit exceeded");
        }

        throw new RateProviderException(
            "{$message}: HTTP {$status}",
            $status,
            null,
            [
                'response' => $body,
            ]
        );
    }

    /**
     * Get base URL for the provider.
     */
    abstract protected function getBaseUrl(): string;

    /**
     * Get API key header name.
     */
    abstract protected function getApiKeyHeader(): string;

    /**
     * Get health check endpoint.
     */
    abstract protected function getHealthCheckEndpoint(): string;
}
