<?php

use App\Filament\Admin\Pages\Dashboard;
use Filament\Pages\Dashboard as BaseDashboard;

it('extends Filament BaseDashboard', function () {
    $reflection = new ReflectionClass(Dashboard::class);
    expect($reflection->getParentClass()->getName())->toBe(BaseDashboard::class);
});

it('has navigation icon property', function () {
    $reflection = new ReflectionClass(Dashboard::class);
    expect($reflection->hasProperty('navigationIcon'))->toBeTrue();

    $property = $reflection->getProperty('navigationIcon');
    expect($property->getValue())->toBe('heroicon-o-home');
});

it('has view property', function () {
    $reflection = new ReflectionClass(Dashboard::class);
    expect($reflection->hasProperty('view'))->toBeTrue();

    $property = $reflection->getProperty('view');
    expect($property->getValue())->toBe('filament.admin.pages.dashboard');
});

it('has getWidgets method', function () {
    expect((new ReflectionClass(Dashboard::class))->hasMethod('getWidgets'))->toBeTrue();
});

it('has getColumns method', function () {
    expect((new ReflectionClass(Dashboard::class))->hasMethod('getColumns'))->toBeTrue();
});

it('getWidgets returns array of widget classes', function () {
    $dashboard = new Dashboard();
    $widgets = $dashboard->getWidgets();

    expect($widgets)->toBeArray();
    expect($widgets)->toHaveCount(7);
    expect($widgets[0])->toBe(App\Filament\Admin\Widgets\PrimaryBasketWidget::class);
    expect($widgets[3])->toBe(App\Filament\Admin\Resources\AccountResource\Widgets\AccountStatsOverview::class);
});

it('getColumns returns responsive column configuration', function () {
    $dashboard = new Dashboard();
    $columns = $dashboard->getColumns();

    expect($columns)->toBeArray();
    expect($columns)->toHaveKey('sm');
    expect($columns)->toHaveKey('md');
    expect($columns)->toHaveKey('xl');
    /** @var array<string, int|string|null> $columns */
    expect($columns['sm'] ?? null)->toBe(1);
    expect($columns['md'] ?? null)->toBe(2);
    expect($columns['xl'] ?? null)->toBe(4);
});
