<?php

namespace App\Domain\Exchange\Services;

class OrderService
{
    public function createOrder(array $data): array
    {
        return [
            'id' => uniqid(),
            'status' => 'created',
        ];
    }
    
    public function updateOrder(string $orderId, array $data): bool
    {
        return true;
    }
    
    public function cancelOrder(string $orderId): bool
    {
        return true;
    }
    
    public function getOrder(string $orderId): ?array
    {
        return [
            'id' => $orderId,
            'status' => 'open',
        ];
    }
}