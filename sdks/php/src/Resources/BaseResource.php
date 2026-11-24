<?php

namespace FinAegis\Resources;

use FinAegis\Client;

abstract class BaseResource
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Make a GET request.
     */
    protected function httpGet(string $path, array $params = []): array
    {
        $options = [];
        if (! empty($params)) {
            $options['query'] = $params;
        }

        return $this->client->request('GET', $path, $options);
    }

    /**
     * Make a POST request.
     *
     * The client adds an Idempotency-Key and repeats it on retries. Pass
     * $idempotencyKey to choose the key yourself.
     */
    protected function httpPost(string $path, array $data = [], ?string $idempotencyKey = null): array
    {
        $options = [];
        if (! empty($data)) {
            $options['json'] = $data;
        }
        if ($idempotencyKey !== null) {
            $options['idempotency_key'] = $idempotencyKey;
        }

        return $this->client->request('POST', $path, $options);
    }

    /**
     * Make a PUT request.
     */
    protected function httpPut(string $path, array $data = []): array
    {
        $options = [];
        if (! empty($data)) {
            $options['json'] = $data;
        }

        return $this->client->request('PUT', $path, $options);
    }

    /**
     * Make a DELETE request.
     */
    protected function httpDelete(string $path): array
    {
        return $this->client->request('DELETE', $path);
    }
}
