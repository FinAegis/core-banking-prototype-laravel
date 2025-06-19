<?php

namespace App\Filament\Admin\Widgets;

use App\Models\BasketAsset;
use Filament\Widgets\Widget;

class PrimaryBasketWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.primary-basket-widget';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 1;
    
    public function getBasketData(): array
    {
        $basket = BasketAsset::where('code', config('baskets.primary', 'PRIMARY'))->first();
        
        if (!$basket) {
            return [
                'exists' => false,
                'currencies' => [
                    ['code' => 'USD', 'name' => 'US Dollar', 'weight' => 40],
                    ['code' => 'EUR', 'name' => 'Euro', 'weight' => 30],
                    ['code' => 'GBP', 'name' => 'British Pound', 'weight' => 15],
                    ['code' => 'CHF', 'name' => 'Swiss Franc', 'weight' => 10],
                    ['code' => 'JPY', 'name' => 'Japanese Yen', 'weight' => 3],
                    ['code' => 'XAU', 'name' => 'Gold', 'weight' => 2],
                ]
            ];
        }
        
        return [
            'exists' => true,
            'basket' => $basket,
            'currencies' => $basket->components()->with('asset')->get()->map(function ($component) {
                return [
                    'code' => $component->asset_code,
                    'name' => $component->asset->name ?? $component->asset_code,
                    'weight' => $component->weight,
                ];
            })->toArray()
        ];
    }
}