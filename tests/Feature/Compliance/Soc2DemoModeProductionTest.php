<?php

declare(strict_types=1);

use App\Domain\Compliance\Services\Certification\EvidenceCollectionService;

// Wave 0C — the SOC 2 evidence collector must never return fabricated demo
// evidence in production (the audit found it returned hardcoded data stamped
// 'app_env' => 'production'). In prod it throws; outside prod (demo on) it
// returns the demo dataset for local/reference use.

it('throws instead of returning fabricated SOC 2 evidence in production', function (): void {
    config(['compliance-certification.soc2.demo_mode' => true]);
    $this->app->detectEnvironment(fn () => 'production');

    expect(fn () => app(EvidenceCollectionService::class)->collectEvidence('2026-Q1'))
        ->toThrow(RuntimeException::class);
});

it('returns demo SOC 2 evidence outside production when demo mode is on', function (): void {
    config(['compliance-certification.soc2.demo_mode' => true]);
    // testing env (non-production) — demo dataset is fine for local/reference.

    $evidence = app(EvidenceCollectionService::class)->collectEvidence('2026-Q1');

    $this->assertIsArray($evidence);
    $this->assertNotEmpty($evidence);
});
