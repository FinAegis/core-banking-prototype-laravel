<?php

declare(strict_types=1);

use App\Filament\Admin\Widgets\DomainHealthWidget;
use Filament\Widgets\StatsOverviewWidget;

describe('DomainHealthWidget', function () {
    it('extends StatsOverviewWidget', function () {
        $reflection = new ReflectionClass(DomainHealthWidget::class);
        expect($reflection->isSubclassOf(StatsOverviewWidget::class))->toBeTrue();
    });

    it('has 60s polling interval', function () {
        $reflection = new ReflectionClass(DomainHealthWidget::class);
        $property = $reflection->getProperty('pollingInterval');
        expect($property->getValue())->toBe('60s');
    });

    it('has getStats method', function () {
        expect((new ReflectionClass(DomainHealthWidget::class))->hasMethod('getStats'))->toBeTrue();
    });

    it('has sort order 5', function () {
        $reflection = new ReflectionClass(DomainHealthWidget::class);
        $property = $reflection->getProperty('sort');
        expect($property->getValue())->toBe(5);
    });

    it('has computeStats private method', function () {
        $reflection = new ReflectionClass(DomainHealthWidget::class);
        expect($reflection->hasMethod('computeStats'))->toBeTrue();
        expect($reflection->getMethod('computeStats')->isPrivate())->toBeTrue();
    });

    it('has buildStats private method', function () {
        $reflection = new ReflectionClass(DomainHealthWidget::class);
        expect($reflection->hasMethod('buildStats'))->toBeTrue();
        expect($reflection->getMethod('buildStats')->isPrivate())->toBeTrue();
    });
});
