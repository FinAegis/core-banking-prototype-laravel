<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\VectorDB;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PineconeProvider implements VectorDatabaseInterface
{
    private Client $client;

    private string $apiKey;

    private string $environment;

    private string $indexName;

    private string $indexHost;

    public function __construct()
    {
        $this->apiKey = config('services.pinecone.api_key', '');
        $this->environment = config('services.pinecone.environment', 'us-east-1');
        $this->indexName = config('services.pinecone.index_name', 'finaegis-ai');
        $this->indexHost = config('services.pinecone.index_host', '');

        $this->client = new Client([
            'timeout' => 30.0,
            'headers' => [
                'Api-Key'      => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function store(string $id, array $vector, array $metadata = []): void
    {
        try {
            $response = $this->client->post($this->getIndexUrl() . '/vectors/upsert', [
                'json' => [
                    'vectors' => [
                        [
                            'id'       => $id,
                            'values'   => $vector,
                            'metadata' => $metadata,
                        ],
                    ],
                ],
            ]);

            Log::info('Vector stored in Pinecone', [
                'id'            => $id,
                'dimensions'    => count($vector),
                'metadata_keys' => array_keys($metadata),
            ]);
        } catch (RequestException $e) {
            Log::error('Failed to store vector in Pinecone', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to store vector: ' . $e->getMessage(), 0, $e);
        }
    }

    public function storeBatch(array $items): void
    {
        if (empty($items)) {
            return;
        }

        try {
            $vectors = array_map(function ($item) {
                return [
                    'id'       => $item['id'],
                    'values'   => $item['vector'],
                    'metadata' => $item['metadata'],
                ];
            }, $items);

            // Pinecone has a limit of 100 vectors per batch
            $chunks = array_chunk($vectors, 100);

            foreach ($chunks as $chunk) {
                $response = $this->client->post($this->getIndexUrl() . '/vectors/upsert', [
                    'json' => [
                        'vectors' => $chunk,
                    ],
                ]);
            }

            Log::info('Batch vectors stored in Pinecone', [
                'count'   => count($items),
                'batches' => count($chunks),
            ]);
        } catch (RequestException $e) {
            Log::error('Failed to store batch vectors in Pinecone', [
                'count' => count($items),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to store batch vectors: ' . $e->getMessage(), 0, $e);
        }
    }

    public function search(array $queryVector, int $topK = 10, array $filters = []): array
    {
        try {
            $requestBody = [
                'vector'          => $queryVector,
                'topK'            => $topK,
                'includeMetadata' => true,
                'includeValues'   => false,
            ];

            if (! empty($filters)) {
                $requestBody['filter'] = $this->buildFilter($filters);
            }

            // Check cache for similar queries
            $cacheKey = 'pinecone:search:' . md5(json_encode($requestBody) ?: '');
            if ($cached = Cache::get($cacheKey)) {
                Log::debug('Pinecone search served from cache');

                return $cached;
            }

            $response = $this->client->post($this->getIndexUrl() . '/query', [
                'json' => $requestBody,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            $results = array_map(function ($match) {
                return [
                    'id'       => $match['id'],
                    'score'    => $match['score'],
                    'metadata' => $match['metadata'] ?? [],
                ];
            }, $data['matches'] ?? []);

            // Cache for 5 minutes
            Cache::put($cacheKey, $results, 300);

            return $results;
        } catch (RequestException $e) {
            Log::error('Failed to search vectors in Pinecone', [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to search vectors: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(string $id): void
    {
        try {
            $response = $this->client->post($this->getIndexUrl() . '/vectors/delete', [
                'json' => [
                    'ids' => [$id],
                ],
            ]);

            Log::info('Vector deleted from Pinecone', ['id' => $id]);
        } catch (RequestException $e) {
            Log::error('Failed to delete vector from Pinecone', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to delete vector: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deleteByFilter(array $filters): int
    {
        try {
            $response = $this->client->post($this->getIndexUrl() . '/vectors/delete', [
                'json' => [
                    'filter' => $this->buildFilter($filters),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $deletedCount = $data['deleted'] ?? 0;

            Log::info('Vectors deleted from Pinecone by filter', [
                'filters'       => $filters,
                'deleted_count' => $deletedCount,
            ]);

            return $deletedCount;
        } catch (RequestException $e) {
            Log::error('Failed to delete vectors by filter from Pinecone', [
                'filters' => $filters,
                'error'   => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to delete vectors by filter: ' . $e->getMessage(), 0, $e);
        }
    }

    public function get(string $id): ?array
    {
        try {
            $response = $this->client->get($this->getIndexUrl() . '/vectors/fetch', [
                'query' => [
                    'ids' => $id,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['vectors'][$id])) {
                $vector = $data['vectors'][$id];

                return [
                    'id'       => $id,
                    'vector'   => $vector['values'],
                    'metadata' => $vector['metadata'] ?? [],
                ];
            }

            return null;
        } catch (RequestException $e) {
            Log::error('Failed to get vector from Pinecone', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function createIndex(string $name, int $dimensions, string $metric = 'cosine'): void
    {
        try {
            // Check if index exists
            $response = $this->client->get("https://api.pinecone.io/indexes/{$name}", [
                'headers' => [
                    'Api-Key' => $this->apiKey,
                ],
            ]);

            Log::info('Pinecone index already exists', ['name' => $name]);
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 404) {
                // Index doesn't exist, create it
                try {
                    $response = $this->client->post('https://api.pinecone.io/indexes', [
                        'headers' => [
                            'Api-Key'      => $this->apiKey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'name'      => $name,
                            'dimension' => $dimensions,
                            'metric'    => $metric,
                            'spec'      => [
                                'serverless' => [
                                    'cloud'  => 'aws',
                                    'region' => $this->environment,
                                ],
                            ],
                        ],
                    ]);

                    Log::info('Pinecone index created', [
                        'name'       => $name,
                        'dimensions' => $dimensions,
                        'metric'     => $metric,
                    ]);
                } catch (RequestException $createError) {
                    Log::error('Failed to create Pinecone index', [
                        'name'  => $name,
                        'error' => $createError->getMessage(),
                    ]);
                    throw new \RuntimeException('Failed to create index: ' . $createError->getMessage(), 0, $createError);
                }
            } else {
                throw new \RuntimeException('Failed to check index: ' . $e->getMessage(), 0, $e);
            }
        }
    }

    public function isAvailable(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        try {
            $response = $this->client->get('https://api.pinecone.io/indexes', [
                'headers' => [
                    'Api-Key' => $this->apiKey,
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::warning('Pinecone availability check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getStats(): array
    {
        try {
            $response = $this->client->get($this->getIndexUrl() . '/describe_index_stats');
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'total_vectors'  => $data['totalVectorCount'] ?? 0,
                'dimensions'     => $data['dimension'] ?? 0,
                'index_fullness' => $data['indexFullness'] ?? 0,
                'namespaces'     => $data['namespaces'] ?? [],
            ];
        } catch (RequestException $e) {
            Log::error('Failed to get Pinecone stats', ['error' => $e->getMessage()]);

            return [
                'total_vectors'  => 0,
                'dimensions'     => 0,
                'index_fullness' => 0,
                'namespaces'     => [],
            ];
        }
    }

    private function getIndexUrl(): string
    {
        if ($this->indexHost) {
            return "https://{$this->indexHost}";
        }

        return "https://{$this->indexName}-{$this->environment}.svc.pinecone.io";
    }

    private function buildFilter(array $filters): array
    {
        // Convert simple key-value filters to Pinecone filter format
        $pineconeFilter = [];

        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $pineconeFilter[$key] = ['$in' => $value];
            } else {
                $pineconeFilter[$key] = ['$eq' => $value];
            }
        }

        return $pineconeFilter;
    }
}
