<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning\Bypasses;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\Mobile\Services\BiometricJWTService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('short-circuits device attestation when review account has bypass flag', function () {
    config()->set('mobile.attestation.enabled', true);

    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                   => $user->id,
        'is_review_account'         => true,
        'bypass_device_attestation' => true,
    ]);

    $logSpy = Log::spy();

    $service = app(BiometricJWTService::class);

    // Use an obviously-bogus attestation — the bypass should short-circuit before
    // the attestation payload is inspected.
    $verified = $service->verifyDeviceAttestationForUser($user, 'bogus', 'ios');

    expect($verified)->toBeTrue();

    $logSpy->shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($user) {
        return $message === 'bypass.fired'
            && ($context['user_id'] ?? null) === $user->id
            && ($context['bypass'] ?? null) === 'device_attestation';
    })->atLeast()->once();
});

it('runs normal attestation verification for regular users', function () {
    config()->set('mobile.attestation.enabled', false);

    $user = User::factory()->create();
    // No AccountFlag row — regular user.

    $service = app(BiometricJWTService::class);

    // Empty attestation fails in demo mode (length check).
    expect($service->verifyDeviceAttestationForUser($user, '', 'ios'))->toBeFalse();

    // Short attestation fails demo-mode length check (ios requires >= 100).
    expect($service->verifyDeviceAttestationForUser($user, str_repeat('x', 10), 'ios'))->toBeFalse();
});
