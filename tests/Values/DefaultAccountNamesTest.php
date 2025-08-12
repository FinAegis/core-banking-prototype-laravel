<?php

use App\Domain\Account\Values\DefaultAccountNames;

it('is an enum', function () {
    $reflection = new ReflectionClass(DefaultAccountNames::class);
    expect($reflection->isEnum())->toBeTrue();
});

it('has expected cases', function () {
    expect(DefaultAccountNames::MAIN->value)->toBe('Main');
    expect(DefaultAccountNames::SAVINGS->value)->toBe('Savings');
    expect(DefaultAccountNames::LOAN->value)->toBe('Loan');
});

it('has default method', function () {
    // Method exists check is redundant - just call it
    expect(DefaultAccountNames::default())->toBe(DefaultAccountNames::MAIN);
});

it('default returns MAIN', function () {
    $default = DefaultAccountNames::default();
    expect($default)->toBe(DefaultAccountNames::MAIN);
    expect($default->value)->toBe('Main');
});

it('has label method', function () {
    // Method exists check is redundant - just call it
    expect(DefaultAccountNames::MAIN->label())->toBe('Main');
});

it('has label method structure', function () {
    $reflection = new ReflectionMethod(DefaultAccountNames::class, 'label');
    expect($reflection->isPublic())->toBeTrue();
    expect((string) $reflection->getReturnType())->toBe('string');
});

it('has correct string values', function () {
    expect(DefaultAccountNames::MAIN->value)->toBe('Main');
    expect(DefaultAccountNames::SAVINGS->value)->toBe('Savings');
    expect(DefaultAccountNames::LOAN->value)->toBe('Loan');
});

it('can get all cases', function () {
    $cases = DefaultAccountNames::cases();
    expect($cases)->toHaveCount(3);
    expect($cases)->toContain(DefaultAccountNames::MAIN);
    expect($cases)->toContain(DefaultAccountNames::SAVINGS);
    expect($cases)->toContain(DefaultAccountNames::LOAN);
});

it('can convert to string', function () {
    expect((string) DefaultAccountNames::MAIN->value)->toBe('Main');
    expect((string) DefaultAccountNames::SAVINGS->value)->toBe('Savings');
    expect((string) DefaultAccountNames::LOAN->value)->toBe('Loan');
});

it('enum values are accessible', function () {
    expect(DefaultAccountNames::MAIN)->toBeInstanceOf(DefaultAccountNames::class);
    expect(DefaultAccountNames::SAVINGS)->toBeInstanceOf(DefaultAccountNames::class);
    expect(DefaultAccountNames::LOAN)->toBeInstanceOf(DefaultAccountNames::class);
});

it('can access value property', function () {
    expect(DefaultAccountNames::MAIN->value)->toBe('Main');
    expect(DefaultAccountNames::SAVINGS->value)->toBe('Savings');
    expect(DefaultAccountNames::LOAN->value)->toBe('Loan');
});
