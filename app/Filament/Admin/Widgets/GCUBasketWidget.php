<?php

namespace App\Filament\Admin\Widgets;

use App\Models\BasketAsset;
use Filament\Widgets\Widget;

class GCUBasketWidget extends PrimaryBasketWidget
{
    protected static string $view = 'filament.admin.widgets.gcu-basket-widget';
    
    public static function canView(): bool
    {
        // Only show if GCU is configured as the primary basket
        return config('baskets.primary_code') === 'GCU';
    }
    
    protected function getViewData(): array
    {
        $data = parent::getBasketData();
        
        // Add GCU-specific information
        $data['title'] = 'GCU (Global Currency Unit) Composition';
        $data['description'] = 'Current allocation of currencies in the Global Currency Unit basket';
        $data['symbol'] = config('baskets.primary_symbol', 'Ǥ');
        
        if ($data['exists'] && isset($data['basket'])) {
            $data['lastRebalanced'] = $data['basket']->last_rebalanced_at?->diffForHumans() ?? 'Never';
            $data['nextRebalance'] = $data['basket']->metadata['next_rebalance'] ?? null;
        }
        
        return $data;
    }
}