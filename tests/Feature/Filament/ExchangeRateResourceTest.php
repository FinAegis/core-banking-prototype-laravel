<?php

declare(strict_types=1);

use App\Domain\Asset\Models\ExchangeRate;
use App\Filament\Admin\Resources\ExchangeRateResource;

beforeEach(function () {
    $this->setUpFilamentWithAuth();
});

describe('Exchange Rate Resource Configuration', function () {
    it('has correct model', function () {
        expect(ExchangeRateResource::getModel())->toBe(ExchangeRate::class);
    });

    it('has correct navigation icon', function () {
        expect(ExchangeRateResource::getNavigationIcon())->toBe('heroicon-o-arrow-path');
    });

    it('has correct navigation group', function () {
        expect(ExchangeRateResource::getNavigationGroup())->toBe('Asset Management');
    });

    it('has correct navigation sort', function () {
        expect(ExchangeRateResource::getNavigationSort())->toBe(2);
    });

    it('has navigation badge', function () {
        expect(ExchangeRateResource::getNavigationBadge())->toBeString();
    });

    it('has navigation badge color', function () {
        expect(ExchangeRateResource::getNavigationBadgeColor())->toBeIn(['success', 'warning', 'danger', 'gray']);
    });

    it('has correct pages', function () {
        $pages = ExchangeRateResource::getPages();
        
        expect($pages)->toBeArray();
        expect($pages)->toHaveKeys(['index', 'create', 'view', 'edit']);
    });

    it('has correct widgets', function () {
        $widgets = ExchangeRateResource::getWidgets();
        
        expect($widgets)->toBeArray();
        expect($widgets)->toContain(ExchangeRateResource\Widgets\ExchangeRateStatsWidget::class);
        expect($widgets)->toContain(ExchangeRateResource\Widgets\ExchangeRateChartWidget::class);
    });

    it('can get plural model label', function () {
        expect(ExchangeRateResource::getPluralModelLabel())->toBe('exchange rates');
    });

    it('can get model label', function () {
        expect(ExchangeRateResource::getModelLabel())->toBe('exchange rate');
    });

    it('can get slug', function () {
        expect(ExchangeRateResource::getSlug())->toBe('exchange-rates');
    });
});

describe('Exchange Rate Model Tests', function () {
    it('can create exchange rate', function () {
        $rate = ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'EUR',
            'rate' => 0.85,
            'source' => 'manual',
            'is_active' => true,
        ]);

        expect($rate)->toBeInstanceOf(ExchangeRate::class);
        expect($rate->from_asset_code)->toBe('USD');
        expect($rate->to_asset_code)->toBe('EUR');
        expect((float) $rate->rate)->toBe(0.85);
        expect($rate->source)->toBe('manual');
        expect($rate->is_active)->toBeTrue();
    });

    it('has from asset relationship', function () {
        $rate = ExchangeRate::factory()->create();
        
        expect($rate->fromAsset())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    });

    it('has to asset relationship', function () {
        $rate = ExchangeRate::factory()->create();
        
        expect($rate->toAsset())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    });

    it('can filter valid rates', function () {
        // Create expired rate
        ExchangeRate::factory()->create([
            'valid_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);
        
        // Create future rate
        ExchangeRate::factory()->create([
            'valid_at' => now()->addDay(),
            'expires_at' => now()->addDays(2),
            'is_active' => true,
        ]);
        
        // Create current valid rate
        ExchangeRate::factory()->create([
            'valid_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        
        $validRates = ExchangeRate::valid()->get();
        
        expect($validRates)->toHaveCount(1);
    });

    it('can filter active rates', function () {
        ExchangeRate::factory()->count(3)->create(['is_active' => true]);
        ExchangeRate::factory()->count(2)->create(['is_active' => false]);
        
        $activeRates = ExchangeRate::active()->get();
        
        expect($activeRates)->toHaveCount(3);
        expect($activeRates->every(fn ($rate) => $rate->is_active === true))->toBeTrue();
    });

    it('can filter by source', function () {
        ExchangeRate::factory()->count(2)->create(['source' => 'manual']);
        ExchangeRate::factory()->count(3)->create(['source' => 'api']);
        ExchangeRate::factory()->count(1)->create(['source' => 'oracle']);
        
        expect(ExchangeRate::bySource('manual')->count())->toBe(2);
        expect(ExchangeRate::bySource('api')->count())->toBe(3);
        expect(ExchangeRate::bySource('oracle')->count())->toBe(1);
    });

    it('can check if rate is expired', function () {
        $expiredRate = ExchangeRate::factory()->create([
            'expires_at' => now()->subDay(),
        ]);
        
        $validRate = ExchangeRate::factory()->create([
            'expires_at' => now()->addDay(),
        ]);
        
        expect($expiredRate->isExpired())->toBeTrue();
        expect($validRate->isExpired())->toBeFalse();
    });

});