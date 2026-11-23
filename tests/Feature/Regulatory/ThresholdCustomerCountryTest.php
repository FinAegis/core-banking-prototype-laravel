<?php

declare(strict_types=1);

use App\Domain\Regulatory\Services\ThresholdMonitoringService;
use App\Models\User;

// users stores the country in country_code; there is no country column.
// Threshold contexts read $user->country, which is always null, so rules
// that depend on the customer's country never saw it.

it('puts the customer country code into the threshold context', function (): void {
    $user = User::factory()->create(['country_code' => 'LT']);

    $service = app(ThresholdMonitoringService::class);
    $context = (new ReflectionMethod($service, 'buildCustomerContext'))->invoke($service, $user);

    expect($context['user']['country'])->toBe('LT');
});
