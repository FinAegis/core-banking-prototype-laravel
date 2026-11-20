<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\EventStoreDashboard;
use Filament\Pages\Page;

describe('EventStoreDashboard', function () {
    it('extends Filament Page', function () {
        $reflection = new ReflectionClass(EventStoreDashboard::class);
        expect($reflection->isSubclassOf(Page::class))->toBeTrue();
    });

    it('has correct navigation icon', function () {
        $reflection = new ReflectionClass(EventStoreDashboard::class);
        $property = $reflection->getProperty('navigationIcon');
        expect($property->getValue())->toBe('heroicon-o-circle-stack');
    });

    it('has correct navigation group', function () {
        $reflection = new ReflectionClass(EventStoreDashboard::class);
        $property = $reflection->getProperty('navigationGroup');
        expect($property->getValue())->toBe('System');
    });

    it('has correct navigation sort', function () {
        $reflection = new ReflectionClass(EventStoreDashboard::class);
        $property = $reflection->getProperty('navigationSort');
        expect($property->getValue())->toBe(11);
    });

    it('has correct view', function () {
        $reflection = new ReflectionClass(EventStoreDashboard::class);
        $property = $reflection->getProperty('view');
        expect($property->getValue())->toBe('filament.admin.pages.event-store-dashboard');
    });

    it('has getHeaderWidgets method', function () {
        expect((new ReflectionClass(EventStoreDashboard::class))->hasMethod('getHeaderWidgets'))->toBeTrue();
    });

    it('has getHeaderWidgetsColumns method', function () {
        expect((new ReflectionClass(EventStoreDashboard::class))->hasMethod('getHeaderWidgetsColumns'))->toBeTrue();
    });
});
