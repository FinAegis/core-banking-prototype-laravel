<?php

declare(strict_types=1);

it('still serves the legacy email+password login when the flag is off', function (): void {
    config(['privy.web_login_enabled' => false]);

    $response = $this->get(route('login'));
    $response->assertOk()
        // Legacy form has password field; Privy form does not
        ->assertSee('name="password"', false);
});

it('renders the Privy view when the flag is on', function (): void {
    config(['privy.web_login_enabled' => true]);

    $response = $this->get(route('login'));
    $response->assertOk()
        ->assertSee('name="email"', false)
        ->assertDontSee('name="password"', false);
});
