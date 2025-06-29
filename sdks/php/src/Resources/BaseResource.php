<?php

namespace FinAegis\Resources;

use FinAegis\Client;

abstract class BaseResource
{
    /** @var Client */
    protected Client $client;
    
    public function __construct(Client $client)
    {
        $this->client = $client;
    }
    
    /**
     * Make a GET request.
     */
    protected function get(string $path, array $params = []): array
    {
        $options = [];
        if (!empty($params)) {
            $options['query'] = $params;
        }
        
        return $this->client->request('GET', $path, $options);
    }
    
    /**
     * Make a POST request.
     */
    protected function post(string $path, array $data = []): array
    {
        $options = [];
        if (!empty($data)) {
            $options['json'] = $data;
        }
        
        return $this->client->request('POST', $path, $options);
    }
    
    /**
     * Make a PUT request.
     */
    protected function put(string $path, array $data = []): array
    {
        $options = [];
        if (!empty($data)) {
            $options['json'] = $data;
        }
        
        return $this->client->request('PUT', $path, $options);
    }
    
    /**
     * Make a DELETE request.
     */
    protected function delete(string $path): array
    {
        return $this->client->request('DELETE', $path);
    }
}