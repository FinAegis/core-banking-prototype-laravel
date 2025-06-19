<?php

use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('registration screen cannot be rendered if support is disabled', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
})->skip(function () {
    return Features::enabled(Features::registration());
}, 'Registration support is enabled.');

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'is_business_customer' => false,
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ]);

    // Check if registration was successful
    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('dashboard', absolute: false));
    
    // Verify user was created
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('new business users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test Business User',
        'email' => 'business@example.com',
        'is_business_customer' => true,
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ]);

    // Check if registration was successful
    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('dashboard', absolute: false));
    
    // Verify user was created
    $this->assertDatabaseHas('users', [
        'email' => 'business@example.com',
        'name' => 'Test Business User',
    ]);
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');
