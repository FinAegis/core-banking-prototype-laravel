<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Activities;

use App\Domain\Exchange\Services\OrderService;
use Workflow\Activity\ActivityInterface;
use Workflow\Activity\ActivityMethod;

#[ActivityInterface]
class CancelOrderActivity
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * Cancel an order in the order book.
     *
     * @param string $orderId Order ID to cancel
     * @return bool Success status
     */
    #[ActivityMethod]
    public function execute(string $orderId): bool
    {
        return $this->orderService->cancelOrder($orderId);
    }
}
