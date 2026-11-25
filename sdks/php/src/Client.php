<?php

namespace FinAegis;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Client
{
    /** The HTTP header the API reads to recognise a repeated request. */
    public const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    private HttpClient $httpClient;

    private string $apiKey;

    private string $baseUrl;

    private LoggerInterface $logger;

    private int $maxRetries;

    private float $timeout;

    /** @var callable|null */
    private $handler;

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
     * Options: environment, base_url, timeout, logger, max_retries (how many
     * times a request is sent again after a connection error, a 5xx or a
     * 429; 0 turns retries off; default 3) and handler (a Guzzle handler,
     * for example a MockHandler in tests).
     *
     * @param  string  $apiKey  API key for authentication
     * @param  array  $options  Configuration options
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
        $this->logger = $options['logger'] ?? new NullLogger;
        $this->handler = $options['handler'] ?? null;

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
            'production' => 'https://api.zelta.app/v2',
            'sandbox' => 'https://api-sandbox.zelta.app/v2',
            'local' => 'http://localhost:8000/api/v2',
        ];

        if (! isset($urls[$environment])) {
            throw new \InvalidArgumentException("Invalid environment: {$environment}");
        }

        return $urls[$environment];
    }

    /**
     * Create HTTP client with retry middleware.
     */
    private function createHttpClient(): HttpClient
    {
        $stack = HandlerStack::create($this->handler);

        // Add retry middleware
        $stack->push(Middleware::retry(
            // Guzzle passes a ConnectException (not a RequestException) when a
            // connection fails or times out, so accept any Throwable here.
            function (
                $retries,
                RequestInterface $request,
                ?ResponseInterface $response = null,
                ?\Throwable $exception = null
            ) {
                // Don't retry if we've exceeded max retries
                if ($retries >= $this->maxRetries) {
                    return false;
                }

                // A POST can move money. Send it again only with the same
                // Idempotency-Key, so the API can recognise the repeat and
                // answer with the first result instead of running it twice.
                $unsafeMethod = in_array(strtoupper($request->getMethod()), ['POST', 'PATCH'], true);
                if ($unsafeMethod && $request->getHeaderLine(self::IDEMPOTENCY_HEADER) === '') {
                    return false;
                }

                // The http_errors middleware sits outside this one, so an error
                // status arrives here as a response, not as an exception.
                if ($response === null && $exception instanceof RequestException && $exception->hasResponse()) {
                    $response = $exception->getResponse();
                }

                // Retry on 5xx errors and rate limits
                if ($response !== null) {
                    $statusCode = $response->getStatusCode();

                    return $statusCode >= 500 || $statusCode === 429;
                }

                // Retry on connection errors and timeouts
                return $exception instanceof RequestException || $exception instanceof ConnectException;
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
            // Guzzle passes the response promise here, not the response.
            function (RequestInterface $request, array $options, PromiseInterface $promise) {
                $promise->then(function (ResponseInterface $response) use ($request) {
                    $this->logger->debug('FinAegis API Response', [
                        'status' => $response->getStatusCode(),
                        'uri' => (string) $request->getUri(),
                    ]);

                    return $response;
                });
            }
        ));

        return new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'handler' => $stack,
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'FinAegis-PHP/'.$this->getVersion(),
            ],
        ]);
    }

    /**
     * Make a request to the API.
     *
     * Every POST carries an Idempotency-Key header. The client makes a new
     * key for each call and sends the same key when it retries the call.
     * Set the `idempotency_key` option to use your own key, or to false to
     * send none; a POST without a key is never retried.
     *
     * @param  string  $method  HTTP method
     * @param  string  $path  API path
     * @param  array  $options  Request options
     * @return array Response data
     *
     * @throws Exceptions\FinAegisException
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $idempotencyKey = $options['idempotency_key'] ?? null;
        unset($options['idempotency_key']);

        $wantsKey = strtoupper($method) === 'POST' && $idempotencyKey !== false;
        if ($wantsKey && ! $this->hasHeader($options, self::IDEMPOTENCY_HEADER)) {
            $options['headers'][self::IDEMPOTENCY_HEADER] = is_string($idempotencyKey) && $idempotencyKey !== ''
                ? $idempotencyKey
                : self::generateIdempotencyKey();
        }

        try {
            $response = $this->httpClient->request($method, $path, $options);
            $body = (string) $response->getBody();

            if (empty($body)) {
                return [];
            }

            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exceptions\FinAegisException('Invalid JSON response: '.json_last_error_msg());
            }

            return $data;
        } catch (RequestException $e) {
            throw $this->handleRequestException($e);
        } catch (ConnectException $e) {
            throw new Exceptions\NetworkException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a random version 4 UUID to use as an Idempotency-Key.
     */
    public static function generateIdempotencyKey(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Whether the request options already set the given header.
     *
     * @param  array<string, mixed>  $options
     */
    private function hasHeader(array $options, string $name): bool
    {
        foreach (array_keys($options['headers'] ?? []) as $header) {
            if (strcasecmp((string) $header, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle Guzzle request exceptions.
     */
    private function handleRequestException(RequestException $e): Exceptions\FinAegisException
    {
        if (! $e->hasResponse()) {
            return new Exceptions\NetworkException($e->getMessage(), 0, $e);
        }

        $response = $e->getResponse();
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        $data = [];
        if (! empty($body)) {
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
