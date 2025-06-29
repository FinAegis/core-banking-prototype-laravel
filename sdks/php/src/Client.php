<?php

namespace FinAegis;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Client
{
    /** @var HttpClient */
    private HttpClient $httpClient;
    
    /** @var string */
    private string $apiKey;
    
    /** @var string */
    private string $baseUrl;
    
    /** @var LoggerInterface */
    private LoggerInterface $logger;
    
    /** @var int */
    private int $maxRetries;
    
    /** @var float */
    private float $timeout;
    
    // Resource properties
    public Resources\Accounts $accounts;
    public Resources\Transfers $transfers;
    public Resources\Transactions $transactions;
    public Resources\ExchangeRates $exchangeRates;
    public Resources\GCU $gcu;
    public Resources\Webhooks $webhooks;
    public Resources\Baskets $baskets;
    
    /**
     * FinAegis API client constructor.
     *
     * @param string $apiKey API key for authentication
     * @param array $options Configuration options
     */
    public function __construct(string $apiKey, array $options = [])
    {
        $this->apiKey = $apiKey ?: $_ENV['FINAEGIS_API_KEY'] ?? '';
        
        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('API key is required');
        }
        
        // Set options with defaults
        $environment = $options['environment'] ?? 'production';
        $this->baseUrl = $this->getBaseUrl($environment, $options['base_url'] ?? null);
        $this->maxRetries = $options['max_retries'] ?? 3;
        $this->timeout = $options['timeout'] ?? 30.0;
        $this->logger = $options['logger'] ?? new NullLogger();
        
        // Create HTTP client with middleware
        $this->httpClient = $this->createHttpClient();
        
        // Initialize resources
        $this->accounts = new Resources\Accounts($this);
        $this->transfers = new Resources\Transfers($this);
        $this->transactions = new Resources\Transactions($this);
        $this->exchangeRates = new Resources\ExchangeRates($this);
        $this->gcu = new Resources\GCU($this);
        $this->webhooks = new Resources\Webhooks($this);
        $this->baskets = new Resources\Baskets($this);
    }
    
    /**
     * Get base URL for the given environment.
     */
    private function getBaseUrl(string $environment, ?string $customUrl): string
    {
        if ($customUrl) {
            return rtrim($customUrl, '/');
        }
        
        $urls = [
            'production' => 'https://api.finaegis.com/v2',
            'sandbox' => 'https://sandbox-api.finaegis.com/v2',
            'local' => 'http://localhost:8000/api/v2',
        ];
        
        if (!isset($urls[$environment])) {
            throw new \InvalidArgumentException("Invalid environment: {$environment}");
        }
        
        return $urls[$environment];
    }
    
    /**
     * Create HTTP client with retry middleware.
     */
    private function createHttpClient(): HttpClient
    {
        $stack = HandlerStack::create();
        
        // Add retry middleware
        $stack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, RequestException $exception = null) {
                // Don't retry if we've exceeded max retries
                if ($retries >= $this->maxRetries) {
                    return false;
                }
                
                // Retry on connection errors
                if ($exception instanceof RequestException) {
                    if ($exception->hasResponse()) {
                        $statusCode = $exception->getResponse()->getStatusCode();
                        // Retry on 5xx errors and rate limits
                        return $statusCode >= 500 || $statusCode === 429;
                    }
                    return true;
                }
                
                return false;
            },
            function ($retries) {
                // Exponential backoff with jitter
                return (int) (pow(2, $retries) * 1000 + rand(0, 1000));
            }
        ));
        
        // Add logging middleware
        $stack->push(Middleware::tap(
            function (RequestInterface $request) {
                $this->logger->debug('FinAegis API Request', [
                    'method' => $request->getMethod(),
                    'uri' => (string) $request->getUri(),
                ]);
            },
            function (RequestInterface $request, array $options, ResponseInterface $response) {
                $this->logger->debug('FinAegis API Response', [
                    'status' => $response->getStatusCode(),
                    'uri' => (string) $request->getUri(),
                ]);
            }
        ));
        
        return new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'handler' => $stack,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'FinAegis-PHP/' . $this->getVersion(),
            ],
        ]);
    }
    
    /**
     * Make a request to the API.
     *
     * @param string $method HTTP method
     * @param string $path API path
     * @param array $options Request options
     * @return array Response data
     * @throws Exceptions\FinAegisException
     */
    public function request(string $method, string $path, array $options = []): array
    {
        try {
            $response = $this->httpClient->request($method, $path, $options);
            $body = (string) $response->getBody();
            
            if (empty($body)) {
                return [];
            }
            
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exceptions\FinAegisException('Invalid JSON response: ' . json_last_error_msg());
            }
            
            return $data;
        } catch (RequestException $e) {
            throw $this->handleRequestException($e);
        }
    }
    
    /**
     * Handle Guzzle request exceptions.
     */
    private function handleRequestException(RequestException $e): Exceptions\FinAegisException
    {
        if (!$e->hasResponse()) {
            return new Exceptions\NetworkException($e->getMessage(), 0, $e);
        }
        
        $response = $e->getResponse();
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        
        $data = [];
        if (!empty($body)) {
            $data = json_decode($body, true) ?: [];
        }
        
        $message = $data['message'] ?? $response->getReasonPhrase();
        $errors = $data['errors'] ?? [];
        
        switch ($statusCode) {
            case 400:
                return new Exceptions\ValidationException($message, $statusCode, $errors);
            case 401:
                return new Exceptions\AuthenticationException($message, $statusCode);
            case 403:
                return new Exceptions\AuthorizationException($message, $statusCode);
            case 404:
                return new Exceptions\NotFoundException($message, $statusCode);
            case 429:
                $retryAfter = $response->getHeader('Retry-After')[0] ?? null;
                return new Exceptions\RateLimitException($message, $statusCode, $retryAfter);
            default:
                return new Exceptions\FinAegisException($message, $statusCode);
        }
    }
    
    /**
     * Get SDK version.
     */
    private function getVersion(): string
    {
        return '1.0.0';
    }
}