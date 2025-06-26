<?php

declare(strict_types=1);

use App\Domain\Asset\Models\Asset;
use App\Filament\Admin\Resources\AssetResource;
use App\Models\User;

beforeEach(function () {
    $this->setUpFilamentWithAuth();
});

describe('Asset Resource Configuration', function () {
    it('has correct model', function () {
        expect(AssetResource::getModel())->toBe(Asset::class);
    });

    it('has correct navigation icon', function () {
        expect(AssetResource::getNavigationIcon())->toBe('heroicon-o-currency-dollar');
    });

    it('has correct navigation group', function () {
        expect(AssetResource::getNavigationGroup())->toBe('Asset Management');
    });

    it('has correct navigation sort', function () {
        expect(AssetResource::getNavigationSort())->toBe(1);
    });

    it('has navigation badge', function () {
        expect(AssetResource::getNavigationBadge())->toBeString();
    });

    it('has navigation badge color', function () {
        expect(AssetResource::getNavigationBadgeColor())->toBeIn(['primary', 'warning']);
    });

    it('has correct pages', function () {
        $pages = AssetResource::getPages();
        
        expect($pages)->toBeArray();
        expect($pages)->toHaveKeys(['index', 'create', 'view', 'edit']);
    });

    it('has correct relations', function () {
        $relations = AssetResource::getRelations();
        
        expect($relations)->toBeArray();
    });

    it('has correct widgets', function () {
        $widgets = AssetResource::getWidgets();
        
        expect($widgets)->toBeArray();
    });

    it('can get plural model label', function () {
        expect(AssetResource::getPluralModelLabel())->toBe('assets');
    });

    it('can get model label', function () {
        expect(AssetResource::getModelLabel())->toBe('asset');
    });

    it('can get slug', function () {
        expect(AssetResource::getSlug())->toBe('assets');
    });
});

describe('Asset Model Tests', function () {
    it('can create asset', function () {
        $asset = Asset::factory()->create([
            'code' => 'TEST',
            'name' => 'Test Asset',
            'type' => 'fiat',
            'precision' => 2,
            'is_active' => true,
        ]);

        expect($asset)->toBeInstanceOf(Asset::class);
        expect($asset->code)->toBe('TEST');
        expect($asset->name)->toBe('Test Asset');
        expect($asset->type)->toBe('fiat');
        expect($asset->precision)->toBe(2);
        expect($asset->is_active)->toBeTrue();
    });

    it('has account balances relationship', function () {
        $asset = Asset::factory()->create();
        
        expect($asset->accountBalances())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('has exchange rates from relationship', function () {
        $asset = Asset::factory()->create();
        
        expect($asset->exchangeRatesFrom())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('has exchange rates to relationship', function () {
        $asset = Asset::factory()->create();
        
        expect($asset->exchangeRatesTo())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('can filter active assets', function () {
        // Count existing active assets first
        $initialActiveCount = Asset::active()->count();
        
        Asset::factory()->count(3)->create(['is_active' => true]);
        Asset::factory()->count(2)->create(['is_active' => false]);
        
        $activeAssets = Asset::active()->get();
        
        expect($activeAssets)->toHaveCount($initialActiveCount + 3);
        expect($activeAssets->every(fn ($asset) => $asset->is_active === true))->toBeTrue();
    });

    it('can filter by type', function () {
        // Count existing assets by type first
        $initialFiatCount = Asset::ofType('fiat')->count();
        $initialCryptoCount = Asset::ofType('crypto')->count();
        $initialCommodityCount = Asset::ofType('commodity')->count();
        
        Asset::factory()->count(2)->create(['type' => 'fiat']);
        Asset::factory()->count(3)->create(['type' => 'crypto']);
        Asset::factory()->count(1)->create(['type' => 'commodity']);
        
        expect(Asset::ofType('fiat')->count())->toBe($initialFiatCount + 2);
        expect(Asset::ofType('crypto')->count())->toBe($initialCryptoCount + 3);
        expect(Asset::ofType('commodity')->count())->toBe($initialCommodityCount + 1);
    });
});