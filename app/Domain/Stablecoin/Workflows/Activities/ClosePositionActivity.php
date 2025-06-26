<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Workflows\Activities;

use App\Domain\Stablecoin\Aggregates\StablecoinAggregate;
use Workflow\Activity;

class ClosePositionActivity extends Activity
{
    /**
     * Close a collateral position
     */
    public function execute(
        string $positionUuid,
        string $reason = 'user_closed'
    ): bool {
        // Close position in aggregate
        $aggregate = StablecoinAggregate::retrieve($positionUuid);
        $aggregate->closePosition($reason);
        $aggregate->persist();
        
        return true;
    }
}