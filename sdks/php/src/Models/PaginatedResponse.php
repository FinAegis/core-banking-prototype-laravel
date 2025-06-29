<?php

namespace FinAegis\Models;

class PaginatedResponse
{
    /** @var array */
    public array $data = [];
    
    /** @var int */
    public int $currentPage;
    
    /** @var int */
    public int $lastPage;
    
    /** @var int */
    public int $perPage;
    
    /** @var int */
    public int $total;
    
    /** @var ?string */
    public ?string $nextPageUrl;
    
    /** @var ?string */
    public ?string $prevPageUrl;
    
    /**
     * @param array $response API response
     * @param string|null $modelClass Model class to instantiate for data items
     */
    public function __construct(array $response, ?string $modelClass = null)
    {
        $this->currentPage = $response['current_page'] ?? 1;
        $this->lastPage = $response['last_page'] ?? 1;
        $this->perPage = $response['per_page'] ?? 20;
        $this->total = $response['total'] ?? 0;
        $this->nextPageUrl = $response['next_page_url'] ?? null;
        $this->prevPageUrl = $response['prev_page_url'] ?? null;
        
        $data = $response['data'] ?? [];
        
        if ($modelClass && class_exists($modelClass)) {
            foreach ($data as $item) {
                $this->data[] = new $modelClass($item);
            }
        } else {
            $this->data = $data;
        }
    }
    
    /**
     * Check if there are more pages.
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }
    
    /**
     * Check if on first page.
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage === 1;
    }
}