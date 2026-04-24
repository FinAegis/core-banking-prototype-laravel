<?php

declare(strict_types=1);

use App\Domain\AccountProvisioning\Seeders\TrustCertSeeder;
use App\Domain\TrustCert\Enums\CertificateStatus;
use App\Domain\TrustCert\Models\Certificate;
use App\Models\User;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates one active certificate for the user', function (): void {
    $user = User::factory()->create();

    app(TrustCertSeeder::class)->seed($user);

    $certs = Certificate::where('user_id', $user->id)->get();
    expect($certs)->toHaveCount(1);

    $cert = $certs->first();
    expect($cert)->not->toBeNull();
    /** @var Certificate $cert */
    expect($cert->status)->toBe(CertificateStatus::ACTIVE);
});

it('is idempotent when seeded twice for the same user', function (): void {
    $user = User::factory()->create();

    app(TrustCertSeeder::class)->seed($user);
    app(TrustCertSeeder::class)->seed($user);

    expect(Certificate::where('user_id', $user->id)->count())->toBe(1);
});
