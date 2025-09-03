<?php

use App\View\Components\AppLayout;

it('extends Component', function () {
    $reflection = new ReflectionClass(AppLayout::class);
    $parentClass = $reflection->getParentClass();
    expect($parentClass)->not->toBe(false);
    if ($parentClass !== false) {
        expect($parentClass->getName())->toBe('Illuminate\View\Component');
    }
});

it('has render method', function () {
    // The render method is always present in components
    $component = new AppLayout();
    expect($component)->toHaveMethod('render');
});

it('render method returns View', function () {
    $reflection = new ReflectionClass(AppLayout::class);
    $method = $reflection->getMethod('render');

    expect((string) $method->getReturnType())->toBe('Illuminate\View\View');
});

it('can be instantiated', function () {
    expect(new AppLayout())->toBeInstanceOf(AppLayout::class);
});

it('has correct class structure', function () {
    $reflection = new ReflectionClass(AppLayout::class);
    expect($reflection->isAbstract())->toBeFalse();
    expect($reflection->isFinal())->toBeFalse();
    expect($reflection->getNamespaceName())->toBe('App\View\Components');
});
