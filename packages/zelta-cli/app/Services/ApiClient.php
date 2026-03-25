<?php

declare(strict_types=1);

namespace ZeltaCli\Services;

use GuzzleHttp\Client;

/**
 * HTTP client for Zelta API communication.
 *
 * Handles authentication, JSON encoding, and provides typed responses.
 */
class ApiClient
{
    private Client $http;

    public function __construct(
        private readonly AuthManager $auth,
        int $timeout = 30,
    ) {
        $apiKey = $this->auth->getApiKey();
        $headers = ['Accept' => 'application/json'];
        if ($apiKey !== null) {
            $headers['Authorization'] = "Bearer {$apiKey}";
        }

        $this->http = new Client([
            'base_uri'    => rtrim($this->auth->getBaseUrl(), '/') . '/',
            'timeout'     => $timeout,
            'headers'     => $headers,
            'http_errors' => false,
        ]);
    }

    /**
     * GET request to the Zelta API.
     *
     * @param array<string, mixed> $query Query parameters
     * @return array{status: int, body: array<string, mixed>}
     */
    public function get(string $path, array $query = []): array
    {
        $response = $this->http->get(ltrim($path, '/'), ['query' => $query]);

        return [
            'status' => $response->getStatusCode(),
            'body'   => $this->decode($response->getBody()->getContents()),
        ];
    }

    /**
     * POST request to the Zelta API.
     *
     * @param array<string, mixed> $data Request body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function post(string $path, array $data = []): array
    {
        $response = $this->http->post(ltrim($path, '/'), ['json' => $data]);

        return [
            'status' => $response->getStatusCode(),
            'body'   => $this->decode($response->getBody()->getContents()),
        ];
    }

    /**
     * DELETE request to the Zelta API.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function delete(string $path): array
    {
        $response = $this->http->delete(ltrim($path, '/'));

        return [
            'status' => $response->getStatusCode(),
            'body'   => $this->decode($response->getBody()->getContents()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['raw' => $body];
    }
}
