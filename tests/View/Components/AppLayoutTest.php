<?php

use App\View\Components\AppLayout;

it('extends Component', function () {
    $reflection = new ReflectionClass(AppLayout::class);
    $parentClass = $reflection->getParentClass();
    expect($parentClass)->not->toBe(false);
    expect($parentClass->getName())->toBe('Illuminate\View\Component');
});

it('has render method', function () {
    // Method exists check is redundant - just verify it's callable
    $component = new AppLayout();
    expect(is_callable([$component, 'render']))->toBeTrue();
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
