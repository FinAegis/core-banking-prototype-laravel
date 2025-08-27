<?php

namespace App\Domain\Governance\Activities;

use App\Domain\Basket\Models\BasketAsset;
use Exception;
use Workflow\Activity;

class UpdateBasketComponentsActivity extends Activity
{
    /**
     * Execute update basket components activity.
     */
    public function execute(string $basketCode, array $composition): void
    {
        /** @var \Illuminate\Database\Eloquent\Model|null $basket */
        $basket = BasketAsset::where('code', $basketCode)->first();

        if (! $basket) {
            throw new Exception("Basket {$basketCode} not found");
        }

        foreach ($composition as $assetCode => $weight) {
            $basket->components()
                ->where('asset_code', $assetCode)
                ->update(['weight' => $weight]);
        }

        $basket->update(['last_rebalanced_at' => now()]);
    }
}
