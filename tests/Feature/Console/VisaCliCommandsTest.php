<?php

declare(strict_types=1);

use App\Domain\VisaCli\Models\VisaCliPayment;

beforeEach(function (): void {
    config(['visacli.enabled' => true]);
    config(['visacli.driver' => 'demo']);
});

it('shows status with demo driver', function (): void {
    $this->artisan('visa:status')
        ->expectsOutputToContain('Visa CLI Status')
        ->expectsOutputToContain('Yes')      // initialized
        ->expectsOutputToContain('demo')     // driver
        ->assertExitCode(0);
});

it('shows disabled warning when not enabled', function (): void {
    config(['visacli.enabled' => false]);

    $this->artisan('visa:status')
        ->expectsOutputToContain('disabled')
        ->assertExitCode(0);
});

it('shows recent payments in status', function (): void {
    VisaCliPayment::create([
        'agent_id'          => 'test-agent',
        'url'               => 'https://example.com/api',
        'amount_cents'      => 500,
        'currency'          => 'USD',
        'status'            => 'completed',
        'payment_reference' => 'ref_status_test',
    ]);

    $this->artisan('visa:status')
        ->expectsOutputToContain('Recent Payments')
        ->expectsOutputToContain('test-agent')
        ->assertExitCode(0);
});

it('enroll command rejects in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('visa:enroll --user=1')
        ->expectsOutputToContain('not available in production')
        ->assertExitCode(1);
});

it('pay command warns when disabled', function (): void {
    config(['visacli.enabled' => false]);

    $this->artisan('visa:pay https://example.com --amount=500')
        ->expectsOutputToContain('disabled')
        ->assertExitCode(1);
});
